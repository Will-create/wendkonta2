<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

if (!function_exists('app')) {
    require __DIR__.'/../../vendor/autoload.php';
    $application = require __DIR__.'/../../bootstrap/app.php';
    $application->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}
if (!app()->environment('testing') || !config('mobile.e2e')
    || config('database.default') !== 'mysql'
    || !str_starts_with(DB::connection()->getDatabaseName(), 'salepro_mobile_e2e_')) {
    throw new RuntimeException('Synthetic fixtures may only be installed in an isolated SalePro E2E database.');
}

try {
DB::statement('SET FOREIGN_KEY_CHECKS=0');
foreach (DB::select('SHOW TABLES') as $table) {
    $name = array_values((array) $table)[0];
    if ($name !== 'migrations') {
        DB::statement('TRUNCATE TABLE `'.str_replace('`', '``', $name).'`');
    }
}
DB::statement('SET FOREIGN_KEY_CHECKS=1');

$insert = function (string $table, array $attributes) {
    $columns = DB::select('SHOW COLUMNS FROM `'.$table.'`');
    $values = [];
    $available = [];
    foreach ($columns as $column) {
        $available[] = $column->Field;
        if ($column->Null === 'YES' || $column->Default !== null || str_contains($column->Extra, 'auto_increment')) {
            continue;
        }
        if (preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|decimal|double|float|bit|year)/', $column->Type)) {
            $values[$column->Field] = 0;
        } elseif ($column->Type === 'json') {
            $values[$column->Field] = '{}';
        } elseif (preg_match('/^(?:date|timestamp)/', $column->Type)) {
            $values[$column->Field] = now()->format('Y-m-d H:i:s');
        } elseif (str_starts_with($column->Type, 'enum(')) {
            preg_match("/'([^']+)'/", $column->Type, $match);
            $values[$column->Field] = $match[1];
        } else {
            $values[$column->Field] = '';
        }
    }
    DB::table($table)->insert(array_merge($values, array_intersect_key($attributes, array_flip($available))));
};
$permissions = json_decode(file_get_contents(base_path('tests/e2e/permissions.json')), true, 512, JSON_THROW_ON_ERROR);
$insert('roles', ['id' => 1, 'name' => 'E2E Owner', 'guard_name' => 'web', 'is_active' => 1]);
$insert('roles', ['id' => 3, 'name' => 'E2E Stock Clerk', 'guard_name' => 'web', 'is_active' => 1]);
foreach ($permissions as $index => $name) {
    $insert('permissions', ['id' => $index + 1, 'name' => $name, 'guard_name' => 'web']);
    DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => $index + 1]);
    if ($name === 'products-index') DB::table('role_has_permissions')->insert(['role_id' => 3, 'permission_id' => $index + 1]);
}
$insert('currencies', ['id' => 1, 'name' => 'CFA', 'code' => 'XOF', 'exchange_rate' => 1]);
$insert('general_settings', ['id' => 1, 'site_title' => 'E2E Warehouse', 'company_name' => 'E2E Warehouse',
    'site_logo' => 'e2e-logo.png', 'currency' => 1, 'currency_position' => 'prefix', 'decimal' => 2,
    'theme' => 'default.css', 'staff_access' => 'all', 'date_format' => 'd/m/Y', 'timezone' => 'UTC',
    'modules' => 'manufacturing', 'without_stock' => 'no', 'is_rtl' => 0, 'developed_by' => 'SalePro POS', 'invoice_format' => 'standard']);
copy(public_path('icons/icon-192x192.png'), public_path('logo/e2e-logo.png'));
chmod(public_path('logo/e2e-logo.png'), 0644);
$insert('languages', ['id' => 1, 'name' => 'English', 'language' => 'en', 'is_default' => 1]);
// UI labels come from source templates, never from production translation rows.
$dictionary = json_decode(file_get_contents(base_path('tests/e2e/dictionary.json')), true, 512, JSON_THROW_ON_ERROR);
foreach (array_chunk($dictionary, 300) as $chunk) {
    DB::table('translations')->insert(array_map(fn ($line) => ['locale' => 'en', 'group' => 'db', 'key' => $line['key'], 'value' => $line['value']], $chunk));
}
$insert('units', ['id' => 1, 'unit_name' => 'Piece', 'unit_code' => 'pc', 'operator' => '*', 'operation_value' => 1, 'is_active' => 1]);
$insert('categories', ['id' => 1, 'name' => 'E2E Category', 'is_active' => 1]);
$insert('categories', ['id' => 2, 'name' => 'E2E Other Category', 'is_active' => 1]);
$insert('brands', ['id' => 1, 'title' => 'E2E Brand', 'is_active' => 1]);
foreach ([1 => 'E2E Main Warehouse', 2 => 'E2E Other Warehouse'] as $id => $name) {
    $insert('warehouses', ['id' => $id, 'name' => $name, 'phone' => '000000000', 'email' => 'warehouse@example.test', 'address' => 'Test address', 'is_active' => 1]);
}
$insert('customer_groups', ['id' => 1, 'name' => 'E2E Customers', 'percentage' => 0, 'is_active' => 1]);
$insert('customers', ['id' => 1, 'name' => 'E2E Customer', 'customer_group_id' => 1, 'phone_number' => '000000001',
    'email' => '', 'address' => 'Test address', 'city' => 'Test City', 'country' => 'Test', 'is_active' => 1,
    'type' => 'regular', 'deposit' => 0, 'expense' => 0, 'points' => 0]);
$insert('suppliers', ['id' => 1, 'name' => 'E2E Supplier', 'company_name' => 'E2E Supplier', 'phone_number' => '000000002', 'email' => '', 'address' => 'Test address', 'city' => 'Test City', 'is_active' => 1]);
$insert('suppliers', ['id' => 2, 'name' => 'Stock', 'company_name' => 'Stock', 'phone_number' => '', 'email' => '', 'address' => '', 'city' => '', 'is_active' => 1]);
$insert('purchase_defaults', ['id' => 1, 'supplier_id' => 2, 'warehouse_id' => 1]);
$insert('billers', ['id' => 1, 'name' => 'E2E Biller', 'company_name' => 'E2E Warehouse', 'phone_number' => '000000003', 'email' => '', 'address' => 'Test address', 'city' => 'Test City', 'country' => 'Test', 'is_active' => 1]);
$insert('accounts', ['id' => 1, 'account_no' => 'E2E-ONLY', 'name' => 'E2E Cash', 'initial_balance' => 1000, 'total_balance' => 1000, 'is_default' => 1, 'is_active' => 1]);
$insert('taxes', ['id' => 1, 'name' => 'No Tax', 'rate' => 0, 'is_active' => 1]);
$insert('pos_setting', ['id' => 1, 'customer_id' => 1, 'warehouse_id' => 1, 'biller_id' => 1, 'product_number' => 12,
    'payment_options' => 'cash,cheque', 'invoice_option' => 'standard', 'thermal_invoice_size' => 80, 'keybord_active' => 0, 'is_table' => 0]);
$insert('reward_point_settings', ['id' => 1, 'is_active' => 0, 'minimum_amount' => 100, 'per_point_amount' => 10]);
foreach ([1 => [1, 'e2e-owner'], 2 => [3, 'e2e-clerk']] as $id => [$role, $name]) {
    $insert('users', ['id' => $id, 'name' => $name, 'email' => $name.'@example.test',
        'password' => Hash::make('e2e-fixture-password'), 'role_id' => $role, 'warehouse_id' => 1, 'biller_id' => 1, 'phone' => '000000000', 'is_active' => 1, 'is_deleted' => 0]);
}
foreach ([1 => ['E2E Oil', 'E2E-OIL', 110, 12.5, 5], 2 => ['E2E Flour', 'E2E-FLOUR', 20, 20, 1]] as $id => [$name, $code, $qty, $price, $alert]) {
    $insert('products', ['id' => $id, 'name' => $name, 'code' => $code, 'type' => 'standard', 'barcode_symbology' => 'C128',
        'category_id' => $id === 1 ? 1 : 2, 'brand_id' => 1, 'unit_id' => 1, 'purchase_unit_id' => 1, 'sale_unit_id' => 1,
        'cost' => 5, 'price' => $price, 'qty' => $qty, 'alert_quantity' => $alert, 'tax_id' => 1, 'tax_method' => 1,
        'is_active' => 1, 'is_imei' => 0, 'is_variant' => 0, 'is_batch' => 0, 'is_diffPrice' => 0, 'is_embeded' => 0, 'in_stock' => 1]);
    $insert('product_warehouse', ['product_id' => $id, 'warehouse_id' => 1, 'qty' => $id === 1 ? 100 : 20, 'price' => $price]);
}
$insert('product_warehouse', ['product_id' => 1, 'warehouse_id' => 2, 'qty' => 10, 'price' => 12.5]);
$insert('cash_registers', ['id' => 1, 'user_id' => 1, 'warehouse_id' => 1, 'cash_in_hand' => 1000, 'status' => 1]);
Cache::flush();
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
} catch (Throwable $error) {
    if (defined('STDERR')) {
        fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    }
    throw $error;
}
