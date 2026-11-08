<?php

/**
 * Rollback check for French labels, delivery-report periods, invoice search
 * by customer name, and the sale redirect after a delivery is created.
 *
 * Run from the repo root: php tests/fr-refinement-check.php
 */

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\SaleController;
use App\Models\Sale;
use App\Models\Translation;
use App\Services\DeliveryProgress;
use App\Translation\CaseInsensitiveTranslator;
use Illuminate\Http\RedirectResponse;
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

function loadLocale(string $locale): void
{
    app()->setLocale($locale);
    $lines = Translation::getTrnaslactionsByLocale($locale);
    app('translator')->addLines($lines, $locale);
}

function saleCountBetween(?string $start, ?string $end): int
{
    $query = Sale::query()->whereNotIn('sale_status', [3, 4]);
    if ($start && $end) {
        $query->whereDate('created_at', '>=', $start)->whereDate('created_at', '<=', $end);
    }

    return (int) $query->count();
}

function deliveryCountBetween(?string $start, ?string $end): int
{
    $query = DB::table('deliveries')
        ->join('sales', 'sales.id', '=', 'deliveries.sale_id')
        ->whereNull('sales.deleted_at');
    if ($start && $end) {
        $query->whereDate('deliveries.created_at', '>=', $start)
            ->whereDate('deliveries.created_at', '<=', $end);
    }

    return (int) $query->count();
}

try {
    check('translator ignores key case', app('translator') instanceof CaseInsensitiveTranslator);

    $general = DB::table('general_settings')->latest('id')->first();
    config([
        'staff_access' => $general->staff_access,
        'date_format' => $general->date_format,
        'decimal' => $general->decimal,
        'addons' => $general->modules,
        'is_packing_slip' => $general->is_packing_slip,
    ]);
    Mail::fake();
    Auth::loginUsingId(1);
    check('admin session', Auth::check() && (int) Auth::id() === 1);

    Translation::forgetCachedTranslations();
    loadLocale('fr');
    check('french customer label', __('db.Customer name') === 'Nom du client', __('db.Customer name'));
    check('french invoice search button', __('db.Search invoice') === 'Rechercher une facture', __('db.Search invoice'));
    check('french empty invoice search', __('db.No invoice matches this search.') === 'Aucun résultat trouvé.', __('db.No invoice matches this search.'));
    check('french january', __('db.month_1') === 'Janvier', __('db.month_1'));
    check('french december', __('db.month_12') === 'Décembre', __('db.month_12'));
    check('french bill-to label', __('db.Show Bill To Info') === 'Afficher le destinataire', __('db.Show Bill To Info'));
    check('french dining table label', __('db.table') === 'Table', __('db.table'));
    check('french fully delivered', __('db.fully delivered') === 'Entièrement livré', __('db.fully delivered'));
    check('french mail setting', __('db.mail setting') === 'Paramètres de messagerie', __('db.mail setting'));
    check('french warehouse placeholder', __('db.Select warehouse...') === 'Sélectionner un entrepôt...', __('db.Select warehouse...'));
    check('french customer placeholder', str_starts_with(__('db.Select customer...'), 'Sélectionner un client'), __('db.Select customer...'));

    loadLocale('en');
    check('english customer label stays available', __('db.Customer name') === 'Customer Name', __('db.Customer name'));
    check('english january stays available', __('db.month_1') === 'January', __('db.month_1'));
    loadLocale('fr');

    DB::beginTransaction();

    $controller = app(DeliveryController::class);

    $current = $controller->report(bootRequest(Request::create('/delivery/report', 'GET')));
    $currentData = $current->getData();
    $expectedStart = date('Y-m-01');
    $expectedEnd = date('Y-m-t');
    check('first visit uses the current month', $currentData['start_date'] === $expectedStart && $currentData['end_date'] === $expectedEnd, $currentData['start_date'].' '.$currentData['end_date']);
    check('first visit selects the current year and month', (string) $currentData['year'] === date('Y') && (string) $currentData['month'] === date('n'));
    check('month names are french', ($currentData['months'][1] ?? '') === 'Janvier' && ($currentData['months'][12] ?? '') === 'Décembre');
    check('year list includes this year and next year', in_array((int) date('Y'), $currentData['years'], true) && in_array((int) date('Y') + 1, $currentData['years'], true));
    check('year list omits the 1970 placeholder', !in_array(1970, $currentData['years'], true));
    check('current month sales count matches the database', (int) $currentData['summary']['sales'] === saleCountBetween($expectedStart, $expectedEnd));

    $yearOnly = $controller->report(bootRequest(Request::create('/delivery/report', 'GET', [
        'year' => '2025',
        'month' => 'all',
    ])))->getData();
    check('year without a month covers that year', $yearOnly['start_date'] === '2025-01-01' && $yearOnly['end_date'] === '2025-12-31' && $yearOnly['month'] === 'all');
    check('2025 sales count matches the database', (int) $yearOnly['summary']['sales'] === saleCountBetween('2025-01-01', '2025-12-31'), (string) $yearOnly['summary']['sales']);
    check('2025 delivery rows match the database', count($yearOnly['deliveries']) === deliveryCountBetween('2025-01-01', '2025-12-31'), (string) count($yearOnly['deliveries']));
    $statusSum = (int) $yearOnly['summary']['not_delivered'] + (int) $yearOnly['summary']['in_progress'] + (int) $yearOnly['summary']['partial'] + (int) $yearOnly['summary']['full'];
    check('2025 status cards add up to the sales', $statusSum === (int) $yearOnly['summary']['sales'], json_encode($yearOnly['summary']));
    $sold = 0.0;
    $delivered = 0.0;
    $remaining = 0.0;
    foreach ($yearOnly['saleRows'] as $row) {
        $sold += (float) $row['sold'];
        $delivered += (float) $row['delivered'];
        $remaining += (float) $row['remaining'];
    }
    check('2025 quantity cards match the sale rows', abs($sold - (float) $yearOnly['summary']['sold']) < 0.01 && abs($delivered - (float) $yearOnly['summary']['delivered']) < 0.01 && abs($remaining - (float) $yearOnly['summary']['remaining']) < 0.01);

    $february = $controller->report(bootRequest(Request::create('/delivery/report', 'GET', [
        'year' => '2024',
        'month' => '2',
    ])))->getData();
    check('february 2024 includes the leap day', $february['start_date'] === '2024-02-01' && $february['end_date'] === '2024-02-29', $february['start_date'].' '.$february['end_date']);
    check('february sales count matches the database', (int) $february['summary']['sales'] === saleCountBetween('2024-02-01', '2024-02-29'));

    $december = $controller->report(bootRequest(Request::create('/delivery/report', 'GET', [
        'year' => '2025',
        'month' => '12',
    ])))->getData();
    check('december ends on the 31st', $december['start_date'] === '2025-12-01' && $december['end_date'] === '2025-12-31');
    check('one month is not wider than its year', (int) $december['summary']['sales'] <= (int) $yearOnly['summary']['sales']);

    $monthOnly = $controller->report(bootRequest(Request::create('/delivery/report', 'GET', [
        'month' => '3',
    ])))->getData();
    check('month without a year uses the current year', $monthOnly['year'] === date('Y') && $monthOnly['start_date'] === date('Y').'-03-01' && $monthOnly['end_date'] === date('Y-m-t', strtotime(date('Y').'-03-01')));

    $legacy = $controller->report(bootRequest(Request::create('/delivery/report', 'GET', [
        'start_date' => '2025-06-01',
        'end_date' => '2025-06-30',
    ])))->getData();
    check('date filter without year or month is preserved', $legacy['start_date'] === '2025-06-01' && $legacy['end_date'] === '2025-06-30' && $legacy['year'] === 'all' && $legacy['month'] === 'all');
    check('legacy june count matches the database', (int) $legacy['summary']['sales'] === saleCountBetween('2025-06-01', '2025-06-30'));

    $complete = $controller->report(bootRequest(Request::create('/delivery/report', 'GET', [
        'year' => 'all',
        'month' => 'all',
    ])))->getData();
    check('all years and all months remove the date limit', $complete['start_date'] === null && $complete['end_date'] === null);
    $completeCount = saleCountBetween(null, null);
    check('complete report counts every sale', (int) $complete['summary']['sales'] === $completeCount, (string) $complete['summary']['sales'].' vs '.$completeCount);
    check('complete report is wider than 2025', (int) $complete['summary']['sales'] >= (int) $yearOnly['summary']['sales']);
    check('complete report deliveries match the database', count($complete['deliveries']) === deliveryCountBetween(null, null));

    $sales = app(SaleController::class);
    $empty = $sales->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET')));
    check('empty invoice search returns nothing', $empty->getData(true) === []);

    $donor = DB::table('sales')->whereNull('deleted_at')->orderByDesc('id')->first();
    $louis = DB::table('customers')->where('name', 'Louis Bertson')->first();
    check('louis bertson exists', $louis !== null && $donor !== null);
    if ($louis && $donor) {
        $louisDate = '2024-03-15 10:00:00';
        $louisReference = 'wk-louis-refinement';
        $louisId = DB::table('sales')->insertGetId([
            'reference_no' => $louisReference,
            'user_id' => $donor->user_id,
            'customer_id' => $louis->id,
            'warehouse_id' => $donor->warehouse_id,
            'biller_id' => $donor->biller_id,
            'item' => 1,
            'total_qty' => 1,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_price' => 1000,
            'grand_total' => 1000,
            'sale_status' => 1,
            'payment_status' => 1,
            'paid_amount' => 1000,
            'created_at' => $louisDate,
            'updated_at' => $louisDate,
        ]);

        $partial = $sales->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
            'customer' => 'Louis',
        ])))->getData(true);
        $names = array_map(fn ($row) => $row['customer'] ?? '', $partial);
        $partialOk = $partial !== []
            && in_array($louisId, array_map('intval', array_column($partial, 'id')), true)
            && count(array_filter($names, fn ($name) => stripos($name, 'Louis') === false)) === 0;
        check('partial customer name returns the louis invoice', $partialOk, json_encode(array_slice($names, 0, 5)));

        $full = $sales->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
            'customer' => 'Louis Bertson',
        ])))->getData(true);
        check('full customer name finds that invoice', count($full) === 1 && (int) $full[0]['id'] === $louisId && $full[0]['customer'] === 'Louis Bertson');

        $byReference = $sales->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
            'reference' => 'louis-refinement',
        ])))->getData(true);
        check('reference search finds the invoice', count($byReference) === 1 && (int) $byReference[0]['id'] === $louisId && $byReference[0]['invoice_url'] !== '');

        $byDate = $sales->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
            'date' => '2024-03-15',
        ])))->getData(true);
        $formatted = date(config('date_format'), strtotime($louisDate));
        $dateIds = array_map('intval', array_column($byDate, 'id'));
        $dateOk = in_array($louisId, $dateIds, true) && count(array_filter($byDate, fn ($row) => ($row['date'] ?? '') !== $formatted)) === 0;
        check('date search returns invoices from that day', $dateOk, $formatted);

        $combined = $sales->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
            'reference' => $louisReference,
            'date' => '2024-03-15',
            'customer' => 'Louis',
        ])))->getData(true);
        check('reference, date and customer combine', count($combined) === 1 && (int) $combined[0]['id'] === $louisId && str_contains($combined[0]['grand_total'], '1'));

        $miss = $sales->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
            'reference' => $louisReference,
            'customer' => 'ZZZ-not-a-customer',
        ])))->getData(true);
        check('a conflicting customer name excludes the invoice', $miss === []);
    }

    $candidate = DB::table('sales as s')
        ->join('product_sales as ps', 'ps.sale_id', '=', 's.id')
        ->whereNull('s.deleted_at')
        ->whereRaw('(ps.qty - IFNULL(ps.return_qty, 0)) >= 2')
        ->whereRaw('(SELECT COUNT(*) FROM product_sales ps2 WHERE ps2.sale_id = s.id) = 1')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))->from('deliveries as d')->whereColumn('d.sale_id', 's.id');
        })
        ->orderByDesc('s.id')
        ->first(['s.id as sale_id', 's.created_at', 'ps.id as product_sale_id', 'ps.qty', 'ps.return_qty']);
    check('delivery fixture exists', $candidate !== null);
    if ($candidate) {
        $saleId = (int) $candidate->sale_id;
        $productSaleId = (int) $candidate->product_sale_id;
        $soldQty = (float) $candidate->qty - (float) ($candidate->return_qty ?? 0);
        $saleDate = date('Y-m-d', strtotime($candidate->created_at));
        $base = [
            'sale_id' => $saleId,
            'address' => 'Refinement check',
            'delivered_by' => 'refinement',
            'recieved_by' => 'refinement',
            'status' => '1',
        ];

        $partialResponse = $controller->store(bootRequest(Request::create('/delivery', 'POST', $base + [
            'note' => 'refinement-partial',
            'deliver_qty' => [$productSaleId => 1],
        ])));
        $partialTarget = $partialResponse instanceof RedirectResponse ? $partialResponse->getTargetUrl() : '';
        check('partial delivery redirects', $partialResponse instanceof RedirectResponse && $partialResponse->getStatusCode() === 302);
        check('partial delivery returns to the sale', str_contains($partialTarget, 'open_sale='.$saleId) && str_contains($partialTarget, 'starting_date='.$saleDate), $partialTarget);
        check('partial delivery does not open the delivery list', !str_contains(parse_url($partialTarget, PHP_URL_PATH) ?: '', 'delivery'));
        check('partial delivery flashes success', session('message') !== null && session('not_permitted') === null, (string) session('message'));
        $afterPartial = DeliveryProgress::linesForSale($saleId);
        $partialLine = $afterPartial[0] ?? null;
        check('partial delivery reduces the remaining quantity', $partialLine && abs((float) $partialLine['remaining'] - ($soldQty - 1)) < 0.00001, json_encode($partialLine));
        $history = $sales->deliveryHistory($saleId)->getData(true);
        $notes = array_column($history['deliveries'] ?? [], 'note');
        check('partial delivery appears in the sale history', in_array('refinement-partial', $notes, true));

        $fullResponse = $controller->store(bootRequest(Request::create('/delivery', 'POST', $base + [
            'note' => 'refinement-complete',
            'deliver_qty' => [$productSaleId => $soldQty - 1],
        ])));
        $fullTarget = $fullResponse instanceof RedirectResponse ? $fullResponse->getTargetUrl() : '';
        check('complete delivery returns to the same sale', str_contains($fullTarget, 'open_sale='.$saleId), $fullTarget);
        $progress = DeliveryProgress::forSales([$saleId])[$saleId];
        check('complete delivery finishes the sale', $progress['status'] === DeliveryProgress::FULL && $progress['remaining'] <= 0.00001, $progress['status'].' remaining='.$progress['remaining']);
        $history = $sales->deliveryHistory($saleId)->getData(true);
        $notes = array_column($history['deliveries'] ?? [], 'note');
        check('complete delivery appears in the sale history', in_array('refinement-complete', $notes, true));

        $beforeRows = (int) DB::table('delivery_products')->count();
        $failed = $controller->store(bootRequest(Request::create('/delivery', 'POST', $base + [
            'note' => 'refinement-failed',
            'deliver_qty' => [$productSaleId => 1],
        ])));
        $failedTarget = $failed instanceof RedirectResponse ? $failed->getTargetUrl() : '';
        check('failed delivery does not redirect to the sale', !str_contains($failedTarget, 'open_sale='));
        check('failed delivery keeps the error flash', session('not_permitted') !== null && session('message') === null, (string) session('not_permitted'));
        check('failed delivery stores nothing', (int) DB::table('delivery_products')->count() === $beforeRows && (int) DB::table('deliveries')->where('note', 'refinement-failed')->count() === 0);
    }

    DB::rollBack();
    check('rollback removes the refinement deliveries', (int) DB::table('deliveries')->where('note', 'like', 'refinement-%')->count() === 0);
} catch (Throwable $e) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    $failures++;
    echo 'FAIL uncaught '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n";
}

echo $failures === 0 ? "ALL CHECKS PASSED\n" : "FAILED {$failures} CHECKS\n";
exit($failures === 0 ? 0 : 1);
