<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// These routes are never registered in production. The database guard also protects
// against an accidentally misconfigured test runner.
$e2eGuard = function () {
    abort_unless(app()->environment('testing') && config('mobile.e2e')
        && config('database.default') === 'mysql'
        && str_starts_with(DB::connection()->getDatabaseName(), 'salepro_mobile_e2e_'), 404);
};
Route::get('/__e2e/status', function () use ($e2eGuard) {
    $e2eGuard();
    return response()->json(['environment' => 'testing', 'isolated' => true])->header('Cache-Control', 'no-store');
});
Route::post('/__e2e/reset', function () use ($e2eGuard) {
    $e2eGuard();
    require base_path('tests/e2e/seed.php');
    return response()->json(['reset' => true]);
})->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
Route::get('/__e2e/state', function () use ($e2eGuard) {
    $e2eGuard();
    return response()->json([
        'products' => DB::table('products')->select('id', 'name', 'code', 'qty', 'price')->orderBy('id')->get(),
        'warehouse_stock' => DB::table('product_warehouse')->select('product_id', 'warehouse_id', 'qty')->orderBy('id')->get(),
        'sales' => DB::table('sales')->whereNull('deleted_at')->select('id', 'reference_no', 'grand_total', 'paid_amount', 'payment_status', 'sale_status')->orderBy('id')->get(),
        'purchases' => DB::table('purchases')->whereNull('deleted_at')->select('id', 'reference_no', 'grand_total', 'paid_amount')->orderBy('id')->get(),
        'payments' => DB::table('payments')->select('id', 'sale_id', 'purchase_id', 'amount')->orderBy('id')->get(),
        'receipts' => DB::table('operation_receipts')->count(),
        'customer_deposits' => DB::table('deposits')->select('id', 'customer_id', 'amount', 'reversed_at')->orderBy('id')->get(),
        'deposit_allocations' => DB::table('customer_deposit_allocations')->orderBy('id')->get(),
    ])->header('Cache-Control', 'no-store');
})->middleware('auth');
Route::get('/__e2e/screens', function () use ($e2eGuard) {
    $e2eGuard();
    $screens = ['/dashboard'];
    foreach (Route::getRoutes() as $route) {
        if (!in_array('GET', $route->methods()) || str_contains($route->uri(), '{') || !str_contains($route->getActionName(), '@')) continue;
        [$class, $method] = explode('@', $route->getActionName());
        if (!str_starts_with($class, 'App\\Http\\Controllers\\') && !str_starts_with($class, 'Modules\\Manufacturing\\')) continue;
        if (preg_match('/migrate|clear|delete|destroy|reset|install|backup|sync|export|download|activate|logout|allProductInStock/i', $method)) continue;
        try {
            $reflection = new ReflectionMethod($class, $method);
            $lines = file($reflection->getFileName());
            $body = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
            if (!preg_match('/return\s+view\s*\(/', $body) || preg_match('/->(?:save|update|delete)\s*\(|::create\s*\(|Artisan::call|file_put_contents|unlink\s*\(/', $body)) continue;
        } catch (Throwable $error) { continue; }
        $screens[] = '/'.$route->uri();
    }
    return response()->json(array_values(array_unique($screens)))->header('Cache-Control', 'no-store');
})->middleware('auth');
