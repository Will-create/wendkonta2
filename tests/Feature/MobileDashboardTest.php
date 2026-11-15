<?php

namespace Tests\Feature;

use App\Http\Controllers\MobileDashboardController;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MobileDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['decimal' => 2, 'currency' => 'XOF', 'staff_access' => 'own']);
        Schema::create('permissions', function (Blueprint $table) { $table->id(); $table->string('name'); });
        Schema::create('role_has_permissions', function (Blueprint $table) { $table->integer('role_id'); $table->integer('permission_id'); });
        Schema::create('warehouses', function (Blueprint $table) { $table->id(); $table->boolean('is_active')->default(true); });
        Schema::create('products', function (Blueprint $table) { $table->id(); $table->string('type')->default('standard'); $table->boolean('is_active')->default(true); $table->decimal('alert_quantity', 14, 4); });
        Schema::create('product_warehouse', function (Blueprint $table) { $table->id(); $table->integer('product_id'); $table->integer('warehouse_id'); $table->decimal('qty', 14, 4); });
        foreach (['sales', 'purchases'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id(); $table->integer('user_id'); $table->integer('warehouse_id');
                $table->decimal('grand_total', 14, 2); $table->decimal('paid_amount', 14, 2);
                $table->integer('payment_status')->default(1); $table->timestamps(); $table->softDeletes();
            });
        }
        foreach (['returns' => 'sale_id', 'return_purchases' => 'purchase_id'] as $table => $key) {
            Schema::create($table, function (Blueprint $table) use ($key) { $table->id(); $table->integer($key); $table->decimal('grand_total', 14, 2); });
        }
        foreach (['sales-index', 'purchases-index', 'products-index'] as $index => $name) {
            DB::table('permissions')->insert(['id' => $index + 1, 'name' => $name]);
            DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => $index + 1]);
        }
        DB::table('warehouses')->insert([['id' => 1], ['id' => 2]]);
        DB::table('products')->insert([['id' => 1, 'alert_quantity' => 5], ['id' => 2, 'alert_quantity' => 1]]);
        DB::table('product_warehouse')->insert([
            ['product_id' => 1, 'warehouse_id' => 1, 'qty' => 3], ['product_id' => 1, 'warehouse_id' => 2, 'qty' => 10],
            ['product_id' => 2, 'warehouse_id' => 1, 'qty' => 20],
        ]);
        DB::table('sales')->insert([
            ['id' => 1, 'user_id' => 1, 'warehouse_id' => 1, 'grand_total' => 100, 'paid_amount' => 30, 'created_at' => '2026-11-10'],
            ['id' => 2, 'user_id' => 9, 'warehouse_id' => 2, 'grand_total' => 200, 'paid_amount' => 50, 'created_at' => '2026-11-10'],
        ]);
        DB::table('purchases')->insert(['id' => 1, 'user_id' => 1, 'warehouse_id' => 1, 'grand_total' => 120, 'paid_amount' => 40, 'created_at' => '2026-11-10']);
        DB::table('returns')->insert(['sale_id' => 1, 'grand_total' => 20]);
        DB::table('return_purchases')->insert(['purchase_id' => 1, 'grand_total' => 10]);
    }

    private function summary(int $warehouse, int $role = 1, ?int $assigned = null)
    {
        $request = Request::create('/dashboard/mobile-summary', 'GET', ['warehouse_id' => $warehouse, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $request->setUserResolver(fn () => new GenericUser(['id' => 9, 'role_id' => $role, 'warehouse_id' => $assigned]));
        return app(MobileDashboardController::class)($request);
    }

    public function test_summary_reconciles_returns_payments_and_selected_warehouse(): void
    {
        $response = $this->summary(1);
        $metrics = $response->getData(true)['metrics'];
        $this->assertSame('70.00', $metrics['customer_due']['value']);
        $this->assertSame('70.00', $metrics['supplier_due']['value']);
        $this->assertSame('23', $metrics['stock_units']['value']);
        $this->assertSame('1', $metrics['low_stock']['value']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_staff_summary_hides_unpermitted_metrics_and_scopes_to_own_warehouse(): void
    {
        DB::table('role_has_permissions')->insert([['role_id' => 3, 'permission_id' => 1], ['role_id' => 3, 'permission_id' => 3]]);
        $data = $this->summary(0, 3, 2)->getData(true);
        $this->assertSame(2, $data['warehouse_id']);
        $this->assertSame('150.00', $data['metrics']['customer_due']['value']);
        $this->assertArrayNotHasKey('supplier_due', $data['metrics']);
    }

    public function test_staff_cannot_request_another_warehouse(): void
    {
        DB::table('role_has_permissions')->insert(['role_id' => 3, 'permission_id' => 3]);
        try {
            $this->summary(1, 3, 2);
            $this->fail('Another warehouse was exposed.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_products_without_stock_are_counted_as_low_but_services_are_excluded(): void
    {
        DB::table('products')->insert([
            ['id' => 3, 'type' => 'standard', 'alert_quantity' => 2],
            ['id' => 4, 'type' => 'service', 'alert_quantity' => 2],
        ]);
        $this->assertSame('2', $this->summary(1)->getData(true)['metrics']['low_stock']['value']);
    }

    public function test_overpayments_do_not_offset_other_customer_debts(): void
    {
        DB::table('sales')->insert(['id' => 3, 'user_id' => 1, 'warehouse_id' => 1, 'grand_total' => 10, 'paid_amount' => 20, 'created_at' => '2026-11-10']);
        $this->assertSame('70.00', $this->summary(1)->getData(true)['metrics']['customer_due']['value']);
    }
}
