<?php

/**
 * Rollback self-check for delivery quantities, sales search, and invoice lookup.
 *
 * Bootstraps the Laravel app against the configured salepro database, writes
 * only inside a transaction, and rolls it back. Quantity checks go through
 * DeliveryController::store / update, which call DeliveryProgress::assertLines.
 *
 * Run from the repo root: php tests/delivery-self-check.php
 */

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ReportController;
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

function flashMessage(): string
{
    return (string) (session('not_permitted') ?: session('message') ?: '');
}

function postDelivery(array $payload)
{
    $request = bootRequest(Request::create('/delivery', 'POST', $payload));

    return app(DeliveryController::class)->store($request);
}

function saleSearch(string $search, string $start, string $end, $warehouseId = null): array
{
    $input = [
        'draw' => 1,
        'start' => 0,
        'length' => 200,
        'starting_date' => $start,
        'ending_date' => $end,
        'search' => ['value' => $search],
        'order' => [['column' => 2, 'dir' => 'desc']],
        'all_permission' => ['sales-index', 'delivery'],
    ];
    if ($warehouseId) {
        $input['warehouse_id'] = $warehouseId;
    }
    $request = bootRequest(Request::create('/sales/sale-data', 'POST', $input));
    ob_start();
    try {
        app(SaleController::class)->saleData($request);
        $json = ob_get_clean();
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        throw $e;
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || !isset($decoded['data'])) {
        throw new RuntimeException('saleData did not return a data list: '.substr((string) $json, 0, 400));
    }

    return $decoded;
}

function saleIds(array $payload): array
{
    $ids = [];
    foreach ($payload['data'] as $row) {
        $ids[] = (int) $row['id'];
    }

    return $ids;
}

function lineFor(int $saleId, int $productSaleId): array
{
    foreach (DeliveryProgress::linesForSale($saleId) as $line) {
        if ((int) $line['product_sale_id'] === $productSaleId) {
            return $line;
        }
    }
    throw new RuntimeException('Product line '.$productSaleId.' is missing from sale '.$saleId);
}

function deliveredSum(int $saleId, int $productSaleId): float
{
    return (float) DB::table('delivery_products')
        ->join('deliveries', 'deliveries.id', '=', 'delivery_products.delivery_id')
        ->where('deliveries.sale_id', $saleId)
        ->where('delivery_products.product_sale_id', $productSaleId)
        ->sum('delivery_products.qty');
}

function deliveryCountForNote(int $saleId, string $note): int
{
    return (int) DB::table('deliveries')->where('sale_id', $saleId)->where('note', $note)->count();
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
        'is_packing_slip' => $general->is_packing_slip,
    ]);
    Mail::fake();
    Auth::loginUsingId(1);
    check('admin session', Auth::check() && (int) Auth::id() === 1);

    DB::beginTransaction();
    $open = true;

    $candidate = DB::table('sales as s')
        ->join('product_sales as ps', 'ps.sale_id', '=', 's.id')
        ->join('products as p', 'p.id', '=', 'ps.product_id')
        ->whereNull('s.deleted_at')
        ->whereRaw('(ps.qty - IFNULL(ps.return_qty, 0)) >= 2')
        ->whereRaw('(SELECT COUNT(*) FROM product_sales ps2 WHERE ps2.sale_id = s.id) = 1')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))->from('deliveries as d')->whereColumn('d.sale_id', 's.id');
        })
        ->orderByDesc('s.id')
        ->first([
            's.id as sale_id',
            's.reference_no',
            's.created_at',
            's.warehouse_id',
            'ps.id as product_sale_id',
            'ps.product_id',
            'ps.qty',
            'ps.return_qty',
            'ps.is_delivered',
            'p.name as product_name',
            'p.is_active as product_active',
        ]);

    if (!$candidate) {
        throw new RuntimeException('No single-line sale with remaining quantity of at least 2.');
    }

    $saleId = (int) $candidate->sale_id;
    $productSaleId = (int) $candidate->product_sale_id;
    $productId = (int) $candidate->product_id;
    $sold = (float) $candidate->qty - (float) ($candidate->return_qty ?? 0);
    $saleDate = date('Y-m-d', strtotime($candidate->created_at));
    $originalDeliveredFlag = $candidate->is_delivered;
    $originalActive = (int) $candidate->product_active;
    echo "FIXTURE sale {$saleId} {$candidate->reference_no} product_sale {$productSaleId} sold {$sold} date {$saleDate}\n";

    $initial = lineFor($saleId, $productSaleId);
    check('fixture remaining is at least 2', $initial['remaining'] + 0.00001 >= 2, 'remaining='.$initial['remaining']);
    check('fixture starts with no delivered quantity', abs($initial['delivered']) < 0.00001, 'delivered='.$initial['delivered']);
    $beforeProgress = DeliveryProgress::forSales([$saleId])[$saleId]['status'];
    check('sale with no delivery lines is not yet delivered', $beforeProgress === DeliveryProgress::NOT_DELIVERED, $beforeProgress);

    $base = [
        'sale_id' => $saleId,
        'address' => 'Self-check address',
        'delivered_by' => 'self-check',
        'recieved_by' => 'self-check',
    ];

    postDelivery($base + [
        'status' => '1',
        'note' => 'self-check-first',
        'deliver_qty' => [$productSaleId => 1],
    ]);
    $sum = deliveredSum($saleId, $productSaleId);
    $afterFirst = lineFor($saleId, $productSaleId);
    check('first delivery stores one unit', abs($sum - 1) < 0.00001, 'sum='.$sum);
    check('first delivery reduces remaining by the accepted quantity', abs($afterFirst['remaining'] - ($sold - $sum)) < 0.00001, 'remaining='.$afterFirst['remaining'].' sold='.$sold.' sum='.$sum);
    $progress = DeliveryProgress::forSales([$saleId])[$saleId];
    check('packing header with quantity remaining is in progress', $progress['status'] === DeliveryProgress::IN_PROGRESS, $progress['status']);
    $list = saleSearch($candidate->reference_no, $saleDate, $saleDate, $candidate->warehouse_id);
    $listRow = null;
    foreach ($list['data'] as $row) {
        if ((int) $row['id'] === $saleId) {
            $listRow = $row;
        }
    }
    check('sales list returns the live sale', $listRow !== null);
    if ($listRow) {
        check(
            'sales list progress badge comes from the delivery quantities',
            str_contains($listRow['delivery_status'], DeliveryProgress::label($progress['status']))
                && str_contains($listRow['delivery_status'], DeliveryProgress::formatQty($progress['delivered']).' / '.DeliveryProgress::formatQty($progress['sold']))
        );
    }

    $firstDelivery = DB::table('deliveries')->where('sale_id', $saleId)->where('note', 'self-check-first')->first();
    check('first delivery row exists', $firstDelivery !== null);
    if ($firstDelivery) {
        $update = bootRequest(Request::create('/delivery/'.$firstDelivery->id, 'POST', [
            'delivery_id' => $firstDelivery->id,
            'reference_no' => $firstDelivery->reference_no,
            'address' => $firstDelivery->address,
            'status' => '3',
            'note' => $firstDelivery->note,
            'delivered_by' => $firstDelivery->delivered_by,
            'recieved_by' => $firstDelivery->recieved_by,
        ]));
        app(DeliveryController::class)->update($update);
        $progress = DeliveryProgress::forSales([$saleId])[$saleId];
        check('closed header with quantity remaining is partially delivered', $progress['status'] === DeliveryProgress::PARTIAL, $progress['status'].' remaining='.$progress['remaining']);
        $partialList = saleSearch($candidate->reference_no, $saleDate, $saleDate, $candidate->warehouse_id);
        $partialRow = null;
        foreach ($partialList['data'] as $row) {
            if ((int) $row['id'] === $saleId) {
                $partialRow = $row;
            }
        }
        check(
            'sales list shows partially delivered from the same quantities',
            $partialRow && str_contains($partialRow['delivery_status'], DeliveryProgress::label(DeliveryProgress::PARTIAL))
        );
    }

    $reportRequest = bootRequest(Request::create('/delivery/report', 'GET', [
        'start_date' => $saleDate,
        'end_date' => $saleDate,
        'warehouse_id' => $candidate->warehouse_id,
        'progress' => 'partial',
    ]));
    $report = app(DeliveryController::class)->report($reportRequest);
    $reportData = $report->getData();
    $summary = $reportData['summary'];
    check('delivery report summary counts every progress state', isset($summary['not_delivered'], $summary['in_progress'], $summary['partial'], $summary['full']));
    check(
        'delivery report progress counts add up to the sales in the filter',
        (int) $summary['not_delivered'] + (int) $summary['in_progress'] + (int) $summary['partial'] + (int) $summary['full'] === (int) $summary['sales'],
        json_encode($summary)
    );
    $reported = null;
    foreach ($reportData['saleRows'] as $row) {
        if ((int) $row['id'] === $saleId) {
            $reported = $row;
        }
    }
    check('partial filter includes this sale', $reported && $reported['status'] === DeliveryProgress::PARTIAL, $reported['status'] ?? 'missing');
    $awaitingHit = null;
    foreach ($reportData['awaiting'] as $item) {
        if ($item['sale'] === $candidate->reference_no && $item['product'] === $afterFirst['name']) {
            $awaitingHit = $item;
        }
    }
    check('awaiting products name this sale, its date, and the quantity still open', $awaitingHit !== null && $awaitingHit['date'] !== '' && abs((float) $awaitingHit['remaining'] - $afterFirst['remaining']) < 0.00001);

    $otherWarehouse = DB::table('warehouses')->where('id', '!=', $candidate->warehouse_id)->value('id');
    if ($otherWarehouse) {
        $otherReport = app(DeliveryController::class)->report(bootRequest(Request::create('/delivery/report', 'GET', [
            'start_date' => $saleDate,
            'end_date' => $saleDate,
            'warehouse_id' => $otherWarehouse,
            'progress' => 'all',
        ])));
        $otherIds = array_map(fn ($row) => (int) $row['id'], $otherReport->getData()['saleRows']);
        check('warehouse filter hides the sale', !in_array($saleId, $otherIds, true));
    }

    $rowsBeforeReject = (int) DB::table('delivery_products')->count();
    $notesBeforeReject = deliveryCountForNote($saleId, 'self-check-over');
    postDelivery($base + [
        'status' => '3',
        'note' => 'self-check-over',
        'deliver_qty' => [$productSaleId => $afterFirst['remaining'] + 1],
    ]);
    check('over-delivery is rejected', str_contains(flashMessage(), 'exceeds the remaining'), flashMessage());
    check('over-delivery stores no delivery', deliveryCountForNote($saleId, 'self-check-over') === $notesBeforeReject);
    check('over-delivery stores no delivery line', (int) DB::table('delivery_products')->count() === $rowsBeforeReject);

    $rowsBeforeDup = (int) DB::table('delivery_products')->count();
    postDelivery($base + [
        'status' => '3',
        'note' => 'self-check-duplicate',
        'deliver_lines' => [
            ['product_sale_id' => $productSaleId, 'qty' => 1],
            ['product_sale_id' => $productSaleId, 'qty' => 1],
        ],
    ]);
    check('duplicate product on one delivery is rejected', str_contains(flashMessage(), 'only once'), flashMessage());
    check('duplicate product stores no delivery', deliveryCountForNote($saleId, 'self-check-duplicate') === 0);
    check('duplicate product stores no delivery line', (int) DB::table('delivery_products')->count() === $rowsBeforeDup);

    postDelivery($base + [
        'status' => '3',
        'note' => 'self-check-second',
        'deliver_lines' => [
            ['product_sale_id' => $productSaleId, 'qty' => 1],
        ],
    ]);
    $sum = deliveredSum($saleId, $productSaleId);
    $afterSecond = lineFor($saleId, $productSaleId);
    check('second delivery stores another unit', abs($sum - 2) < 0.00001, 'sum='.$sum);
    check('second delivery reduces remaining again', abs($afterSecond['remaining'] - ($sold - $sum)) < 0.00001, 'remaining='.$afterSecond['remaining']);

    if ($afterSecond['remaining'] > 0.00001) {
        postDelivery($base + [
            'status' => '3',
            'note' => 'self-check-rest',
            'deliver_lines' => [
                ['product_sale_id' => $productSaleId, 'qty' => $afterSecond['remaining']],
            ],
        ]);
    }
    $sum = deliveredSum($saleId, $productSaleId);
    $afterFull = lineFor($saleId, $productSaleId);
    check('consuming the remainder leaves nothing to deliver', abs($afterFull['remaining']) < 0.00001 && abs($sum - $sold) < 0.00001, 'remaining='.$afterFull['remaining'].' sum='.$sum.' sold='.$sold);
    $flag = DB::table('product_sales')->where('id', $productSaleId)->value('is_delivered');
    check('fully consumed line is marked delivered', (int) $flag === 1, 'is_delivered='.var_export($flag, true));
    $fullProgress = DeliveryProgress::forSales([$saleId])[$saleId];
    check('sale is fully delivered once the line quantity is consumed', $fullProgress['status'] === DeliveryProgress::FULL, $fullProgress['status']);

    $rowsBeforeFullReject = (int) DB::table('delivery_products')->count();
    postDelivery($base + [
        'status' => '3',
        'note' => 'self-check-already-full',
        'deliver_qty' => [$productSaleId => 1],
    ]);
    check('fully delivered line is rejected', str_contains(flashMessage(), 'already fully delivered'), flashMessage());
    check('fully delivered rejection stores nothing', deliveryCountForNote($saleId, 'self-check-already-full') === 0 && (int) DB::table('delivery_products')->count() === $rowsBeforeFullReject);

    $historyResponse = app(SaleController::class)->deliveryHistory($saleId);
    $history = $historyResponse->getData(true);
    $byNote = [];
    foreach ($history['deliveries'] as $delivery) {
        $byNote[$delivery['note']] = $delivery;
    }
    check('history lists the first delivery', isset($byNote['self-check-first']));
    check('history lists the second delivery', isset($byNote['self-check-second']));
    foreach (['self-check-first', 'self-check-second'] as $note) {
        if (!isset($byNote[$note])) {
            continue;
        }
        $delivery = $byNote[$note];
        $saved = DB::table('deliveries')->where('sale_id', $saleId)->where('note', $note)->first();
        $itemQty = null;
        foreach ($delivery['items'] as $item) {
            if ((int) $item['product_sale_id'] === $productSaleId) {
                $itemQty = (float) $item['qty'];
            }
        }
        check($note.' history has reference, date, status, note, and quantity', $saved
            && $delivery['reference_no'] === $saved->reference_no
            && $delivery['date'] !== ''
            && (string) $delivery['status'] === (string) $saved->status
            && $delivery['note'] === $note
            && abs((float) $itemQty - 1) < 0.00001
            && array_key_exists('remaining', $delivery['items'][0] ?? []));
    }

    $deleted = DB::table('sales')->whereNotNull('deleted_at')->whereNotNull('reference_no')->orderByDesc('id')->first(['id', 'reference_no', 'created_at']);
    check('a soft-deleted sale exists to search', $deleted !== null);
    if ($deleted) {
        $deletedDate = date('Y-m-d', strtotime($deleted->created_at));
        $deletedSearch = saleSearch($deleted->reference_no, $deletedDate, $deletedDate);
        check(
            'sales search excludes the soft-deleted sale',
            !in_array((int) $deleted->id, saleIds($deletedSearch), true),
            'filtered='.$deletedSearch['recordsFiltered']
        );
        $deletedLookup = app(SaleController::class)->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
            'reference' => $deleted->reference_no,
            'date' => $deletedDate,
        ])));
        $deletedRows = json_decode($deletedLookup->getContent(), true);
        $deletedIds = array_map(fn ($row) => (int) $row['id'], $deletedRows);
        check('invoice lookup excludes the soft-deleted sale', !in_array((int) $deleted->id, $deletedIds, true));
    }

    DB::table('products')->where('id', $productId)->update(['is_active' => 0]);
    $inactiveSearch = saleSearch($candidate->product_name, $saleDate, $saleDate);
    check(
        'deactivated product name still finds the historical sale',
        in_array($saleId, saleIds($inactiveSearch), true),
        'matches='.implode(',', saleIds($inactiveSearch))
    );

    $byReference = json_decode(app(SaleController::class)->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
        'reference' => $candidate->reference_no,
    ])))->getContent(), true);
    $referenceHit = null;
    foreach ($byReference as $row) {
        if ((int) $row['id'] === $saleId) {
            $referenceHit = $row;
        }
    }
    check('invoice lookup by reference returns the sale and an invoice url', $referenceHit && $referenceHit['invoice_url'] === route('sale.invoice', $saleId), $referenceHit['invoice_url'] ?? 'missing');

    $byDate = json_decode(app(SaleController::class)->invoiceLookup(bootRequest(Request::create('/sales/invoice-lookup', 'GET', [
        'date' => $saleDate,
    ])))->getContent(), true);
    $dateHit = null;
    foreach ($byDate as $row) {
        if ((int) $row['id'] === $saleId) {
            $dateHit = $row;
        }
    }
    check('invoice lookup by date returns the sale and an invoice url', $dateHit && $dateHit['invoice_url'] === route('sale.invoice', $saleId), $dateHit['invoice_url'] ?? 'missing');

    $monthStart = date('Y-m').'-01';
    $today = date('Y-m-d');
    $warehouseView = app(ReportController::class)->warehouseReport(bootRequest(Request::create('/reports/warehouse', 'POST', [
        'warehouse_id' => $candidate->warehouse_id,
        'start_date' => $monthStart,
        'end_date' => $today,
    ])));
    $warehouseData = $warehouseView->getData();
    check(
        'warehouse report accepts the chosen warehouse and the current month',
        (string) $warehouseData['warehouse_id'] === (string) $candidate->warehouse_id
            && $warehouseData['start_date'] === $monthStart
            && $warehouseData['end_date'] === $today
    );

    DB::rollBack();
    $open = false;

    $afterLines = (int) DB::table('delivery_products')->count();
    $afterDeliveries = (int) DB::table('deliveries')->count();
    $restoredFlag = DB::table('product_sales')->where('id', $productSaleId)->value('is_delivered');
    $restoredActive = (int) DB::table('products')->where('id', $productId)->value('is_active');
    check('rollback leaves delivery_products unchanged', $afterLines === $beforeLines, "before={$beforeLines} after={$afterLines}");
    check('rollback leaves deliveries unchanged', $afterDeliveries === $beforeDeliveries, "before={$beforeDeliveries} after={$afterDeliveries}");
    check('rollback restores is_delivered', (string) $restoredFlag === (string) $originalDeliveredFlag, 'now='.var_export($restoredFlag, true).' was='.var_export($originalDeliveredFlag, true));
    check('rollback restores product is_active', $restoredActive === $originalActive, "now={$restoredActive} was={$originalActive}");
} catch (Throwable $e) {
    $failures++;
    echo 'FAIL uncaught '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n";
} finally {
    if ($open && DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}

$afterLines = (int) DB::table('delivery_products')->count();
check('delivery_products count matches the pre-check snapshot', $afterLines === $beforeLines, "before={$beforeLines} after={$afterLines}");

echo $failures === 0 ? "ALL CHECKS PASSED\n" : "FAILED {$failures} CHECKS\n";
exit($failures === 0 ? 0 : 1);
