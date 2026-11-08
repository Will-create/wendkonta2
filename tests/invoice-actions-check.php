<?php

/**
 * Dashboard invoice search returns delivery status and the actions that open
 * the sale, its delivery history, and add-delivery.
 *
 * Run from the repo root: php tests/invoice-actions-check.php
 */

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\SaleController;
use App\Services\DeliveryProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;

function check(string $name, bool $condition, string $detail = ''): void
{
    global $failures;
    if ($condition) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}".($detail !== '' ? " {$detail}" : '')."\n";
}

function bootRequest(Request $request): Request
{
    if (!session()->isStarted()) {
        session()->start();
    }
    $request->setLaravelSession(session()->driver());
    app()->instance('request', $request);
    session()->forget('not_permitted');
    session()->forget('message');

    return $request;
}

$beforeLines = (int) DB::table('delivery_products')->count();
$beforeDeliveries = (int) DB::table('deliveries')->count();
$open = false;

try {
    $general = DB::table('general_settings')->latest('id')->first();
    config([
        'staff_access' => $general->staff_access,
        'date_format' => $general->date_format,
        'decimal' => $general->decimal,
        'addons' => $general->modules,
    ]);
    Mail::fake();
    Auth::loginUsingId(1);
    check('admin session', Auth::check() && (int) Auth::id() === 1);

    $dashboard = file_get_contents(resource_path('views/backend/index.blade.php'));
    foreach ([
        'row.invoice_url' => 'invoice link',
        'invoice-link' => 'invoice link class',
        'row.details_url' => 'sale details action',
        'sale-details-link' => 'sale details class',
        'row.history_url' => 'delivery history action',
        'delivery-history-link' => 'delivery history class',
        'row.add_delivery_url' => 'add delivery action',
        'add-delivery-link' => 'add delivery class',
        'row.delivery_status' => 'delivery status text',
        'db.Delivery Status' => 'delivery status column',
    ] as $needle => $label) {
        check('dashboard result markup keeps '.$label, str_contains($dashboard, $needle));
    }

    $salesPage = file_get_contents(resource_path('views/backend/sale/index.blade.php'));
    check('sales page opens the sale from open_sale', str_contains($salesPage, "salePageParams.get('open_sale')"));
    check('sales page opens delivery history from the invoice action', str_contains($salesPage, "salePageParams.get('show') === 'delivery-history'"));
    check('sales page opens add-delivery from the invoice action', str_contains($salesPage, "salePageParams.get('add_delivery')") && str_contains($salesPage, 'openAddDelivery(addDeliveryId)'));

    DB::beginTransaction();
    $open = true;

    $clean = DB::table('sales as s')
        ->whereNull('s.deleted_at')
        ->whereNotNull('s.reference_no')
        ->where('s.reference_no', '!=', '')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))->from('deliveries as d')->whereColumn('d.sale_id', 's.id');
        })
        ->orderByDesc('s.id')
        ->first(['s.id', 's.reference_no']);
    check('a sale with no delivery exists', $clean !== null);
    if ($clean) {
        $cleanRows = app(SaleController::class)->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
            'reference' => $clean->reference_no,
        ])))->getData(true);
        $cleanHit = null;
        foreach ($cleanRows as $row) {
            if ((int) $row['id'] === (int) $clean->id) {
                $cleanHit = $row;
            }
        }
        $cleanProgress = DeliveryProgress::forSales([(int) $clean->id])[(int) $clean->id];
        check(
            'invoice lookup reports the undelivered sale status',
            $cleanHit
                && $cleanHit['delivery_status_key'] === $cleanProgress['status']
                && $cleanHit['delivery_status'] === DeliveryProgress::label($cleanProgress['status'])
                && $cleanHit['invoice_url'] === route('sale.invoice', $clean->id),
            json_encode($cleanHit)
        );
    }

    $candidate = DB::table('sales as s')
        ->join('product_sales as ps', 'ps.sale_id', '=', 's.id')
        ->whereNull('s.deleted_at')
        ->whereNotNull('s.reference_no')
        ->whereRaw('(ps.qty - IFNULL(ps.return_qty, 0)) >= 2')
        ->whereRaw('(SELECT COUNT(*) FROM product_sales ps2 WHERE ps2.sale_id = s.id) = 1')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))->from('deliveries as d')->whereColumn('d.sale_id', 's.id');
        })
        ->orderByDesc('s.id')
        ->first(['s.id as sale_id', 's.reference_no', 's.created_at', 'ps.id as product_sale_id']);

    if (!$candidate) {
        throw new RuntimeException('No single-line sale with remaining quantity of at least 2.');
    }

    $saleId = (int) $candidate->sale_id;
    $saleDate = date('Y-m-d', strtotime($candidate->created_at));
    app(DeliveryController::class)->store(bootRequest(Request::create('/delivery/store', 'POST', [
        'sale_id' => $saleId,
        'address' => 'Invoice action address',
        'status' => '1',
        'note' => '',
        'delivered_by' => 'invoice-check',
        'recieved_by' => 'invoice-check',
        'deliver_qty' => [(int) $candidate->product_sale_id => 1],
    ])));
    check('partial delivery was saved for the lookup', session('not_permitted') === null, (string) session('not_permitted'));

    $progress = DeliveryProgress::forSales([$saleId])[$saleId];
    check('known delivery state is in progress', $progress['status'] === DeliveryProgress::IN_PROGRESS, $progress['status']);

    $rows = app(SaleController::class)->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
        'reference' => $candidate->reference_no,
    ])))->getData(true);
    $hit = null;
    foreach ($rows as $row) {
        if ((int) $row['id'] === $saleId) {
            $hit = $row;
        }
    }
    check('invoice lookup finds the sale', $hit !== null);
    if ($hit) {
        check('payload delivery status matches the sale', $hit['delivery_status_key'] === $progress['status'] && $hit['delivery_status'] === DeliveryProgress::label($progress['status']), json_encode([$hit['delivery_status_key'] ?? null, $hit['delivery_status'] ?? null]));
        check('payload keeps the printable invoice', $hit['invoice_url'] === route('sale.invoice', $saleId), $hit['invoice_url'] ?? '');
        check('payload identifies the sale details', str_contains($hit['details_url'], 'open_sale='.$saleId) && str_contains($hit['details_url'], 'starting_date='.$saleDate), $hit['details_url'] ?? '');
        check('payload identifies the delivery history', str_contains($hit['history_url'], 'open_sale='.$saleId) && str_contains($hit['history_url'], 'show=delivery-history'), $hit['history_url'] ?? '');
        check('payload identifies add-delivery', str_contains($hit['add_delivery_url'], 'add_delivery='.$saleId) && str_contains($hit['add_delivery_url'], 'starting_date='.$saleDate), $hit['add_delivery_url'] ?? '');
    }

    DB::rollBack();
    $open = false;
} catch (Throwable $e) {
    $failures++;
    echo 'FAIL uncaught '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n";
} finally {
    if ($open && DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}

check('delivery count matches the pre-check snapshot', (int) DB::table('deliveries')->count() === $beforeDeliveries);
check('delivery line count matches the pre-check snapshot', (int) DB::table('delivery_products')->count() === $beforeLines);

echo $failures === 0 ? "ALL CHECKS PASSED\n" : "FAILED {$failures} CHECKS\n";
exit($failures === 0 ? 0 : 1);
