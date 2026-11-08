<?php

/**
 * Warehouse report stock listing follows the warehouse and product filters
 * and returns the stored product_warehouse quantities.
 *
 * Run from the repo root: php tests/warehouse-stock-check.php
 */

use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    return $request;
}

function storedStock(int $warehouseId, string $product = ''): array
{
    $query = DB::table('product_warehouse')
        ->join('products', 'products.id', '=', 'product_warehouse.product_id')
        ->where('product_warehouse.warehouse_id', $warehouseId)
        ->select('product_warehouse.id', 'product_warehouse.qty', 'products.name', 'products.code');
    if ($product !== '') {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $product).'%';
        $query->where(function ($inner) use ($like) {
            $inner->where('products.name', 'LIKE', $like)
                ->orWhere('products.code', 'LIKE', $like);
        });
    }

    $rows = [];
    foreach ($query->orderBy('product_warehouse.id')->get() as $row) {
        $rows[(int) $row->id] = $row;
    }

    return $rows;
}

function listedStock(int $warehouseId, string $product = ''): array
{
    $payload = app(ReportController::class)->warehouseStockData(bootRequest(Request::create('/report/warehouse-stock-data', 'POST', [
        'warehouse_id' => $warehouseId,
        'product' => $product,
    ])))->getData(true);

    return $payload;
}

$beforeLines = (int) DB::table('delivery_products')->count();
$beforeDeliveries = (int) DB::table('deliveries')->count();

try {
    Auth::loginUsingId(1);
    check('admin session', Auth::check() && (int) Auth::id() === 1);

    $view = file_get_contents(resource_path('views/backend/report/warehouse_report.blade.php'));
    check('warehouse report has a stock tab', str_contains($view, 'id="warehouse-stock"') && str_contains($view, 'db.Stock Quantity'));
    check('stock tab filters by warehouse and product', str_contains($view, 'warehouse-stock-data') && str_contains($view, 'd.warehouse_id = warehouse_id') && str_contains($view, 'id="stock-product"'));

    $warehouseIds = DB::table('product_warehouse')
        ->select('warehouse_id', DB::raw('COUNT(*) as rows_count'))
        ->groupBy('warehouse_id')
        ->having('rows_count', '>=', 2)
        ->orderByDesc('rows_count')
        ->pluck('warehouse_id');
    check('a warehouse has more than one stock row', $warehouseIds->count() > 0);

    $chosenWarehouse = 0;
    $fragment = '';
    $expected = [];
    $excludedId = 0;
    foreach ($warehouseIds as $warehouseId) {
        $all = storedStock((int) $warehouseId);
        if (count($all) < 2) {
            continue;
        }
        $candidates = [];
        foreach ($all as $row) {
            $code = trim((string) $row->code);
            $name = trim((string) $row->name);
            if ($code !== '' && !preg_match('/[%_\\\\]/', $code)) {
                $candidates[] = $code;
            }
            if (mb_strlen($name) >= 3) {
                $piece = mb_substr($name, 0, 4);
                if (!preg_match('/[%_\\\\]/u', $piece)) {
                    $candidates[] = $piece;
                }
            }
        }
        foreach (array_unique($candidates) as $candidate) {
            $matched = storedStock((int) $warehouseId, $candidate);
            if (count($matched) > 0 && count($matched) < count($all)) {
                $chosenWarehouse = (int) $warehouseId;
                $fragment = $candidate;
                $expected = $matched;
                foreach ($all as $id => $row) {
                    if (!isset($matched[$id])) {
                        $excludedId = (int) $id;
                        break;
                    }
                }
                break 2;
            }
        }
    }

    check('product filter matches some stock and excludes some', $chosenWarehouse > 0 && $fragment !== '' && $excludedId > 0, 'warehouse='.$chosenWarehouse.' fragment='.$fragment);
    if ($chosenWarehouse > 0 && $fragment !== '') {
        $listed = listedStock($chosenWarehouse, $fragment);
        $byId = [];
        foreach ($listed['data'] as $row) {
            $byId[(int) $row['id']] = $row;
        }
        check('filtered listing count equals stored stock rows', (int) $listed['recordsFiltered'] === count($expected) && count($byId) === count($expected), 'listed='.count($byId).' stored='.count($expected));
        $qtyMatch = true;
        $qtyDetail = '';
        foreach ($expected as $id => $row) {
            if (!isset($byId[$id]) || abs((float) $byId[$id]['qty'] - (float) $row->qty) > 0.00001) {
                $qtyMatch = false;
                $qtyDetail = 'id='.$id.' listed='.($byId[$id]['qty'] ?? 'missing').' stored='.$row->qty;
                break;
            }
            if ((int) $byId[$id]['warehouse_id'] !== $chosenWarehouse) {
                $qtyMatch = false;
                $qtyDetail = 'id='.$id.' warehouse='.$byId[$id]['warehouse_id'];
                break;
            }
        }
        check('filtered quantities match stored stock', $qtyMatch, $qtyDetail);
        check('filter excludes a stock row from the same warehouse', !isset($byId[$excludedId]), 'excluded='.$excludedId);

        $unfiltered = listedStock($chosenWarehouse);
        $storedAll = storedStock($chosenWarehouse);
        check('warehouse filter without a product returns every stored row', (int) $unfiltered['recordsFiltered'] === count($storedAll), 'listed='.$unfiltered['recordsFiltered'].' stored='.count($storedAll));
    }
} catch (Throwable $e) {
    $failures++;
    echo 'FAIL uncaught '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n";
}

check('delivery count stayed unchanged', (int) DB::table('deliveries')->count() === $beforeDeliveries);
check('delivery line count stayed unchanged', (int) DB::table('delivery_products')->count() === $beforeLines);

echo $failures === 0 ? "ALL CHECKS PASSED\n" : "FAILED {$failures} CHECKS\n";
exit($failures === 0 ? 0 : 1);
