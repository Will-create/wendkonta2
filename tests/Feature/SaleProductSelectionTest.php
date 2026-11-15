<?php

namespace Tests\Feature;

use App\Http\Controllers\SaleController;
use App\Http\Requests\Sale\StoreSaleRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleProductSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('standard');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('sale_unit_id')->nullable();
            $table->decimal('qty', 15, 4)->default(0);
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('wholesale_price', 15, 4)->default(0);
            $table->decimal('cost', 15, 4)->default(0);
            $table->boolean('promotion')->default(false);
            $table->decimal('promotion_price', 15, 4)->default(0);
            $table->date('last_date')->nullable();
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->integer('tax_method')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_imei')->default(false);
            $table->boolean('is_diffPrice')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->boolean('is_embeded')->default(false);
            $table->boolean('is_batch')->default(false);
            $table->text('product_list')->nullable();
            $table->text('qty_list')->nullable();
            $table->text('extras')->nullable();
            $table->timestamps();
        });

        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('product_batch_id')->nullable();
            $table->text('imei_number')->nullable();
            $table->decimal('qty', 15, 4)->default(0);
            $table->decimal('price', 15, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_name');
            $table->unsignedBigInteger('base_unit')->nullable();
            $table->string('operator')->default('*');
            $table->decimal('operation_value', 15, 4)->default(1);
        });

        Schema::create('general_settings', function (Blueprint $table) {
            $table->id();
            $table->text('modules')->nullable();
            $table->string('without_stock')->default('no');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('type')->nullable();
            $table->integer('points')->default(0);
            $table->decimal('expense', 15, 4)->default(0);
            $table->timestamps();
        });
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');
            $table->boolean('status')->default(true);
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('biller_id')->nullable();
            $table->integer('item');
            $table->decimal('total_qty', 15, 4)->default(0);
            $table->decimal('total_discount', 15, 4)->default(0);
            $table->decimal('total_tax', 15, 4)->default(0);
            $table->decimal('total_price', 15, 4)->default(0);
            $table->decimal('order_tax_rate', 15, 4)->default(0);
            $table->decimal('order_tax', 15, 4)->default(0);
            $table->decimal('order_discount', 15, 4)->default(0);
            $table->decimal('shipping_cost', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->integer('sale_status');
            $table->integer('payment_status');
            $table->decimal('paid_amount', 15, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_batch_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->text('imei_number')->nullable();
            $table->decimal('qty', 15, 4);
            $table->decimal('dqty', 15, 4);
            $table->unsignedBigInteger('sale_unit_id')->nullable();
            $table->decimal('net_unit_price', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('tax_rate', 15, 4)->default(0);
            $table->decimal('tax', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();
        });
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('belongs_to');
            $table->string('name');
            $table->string('type');
        });
        Schema::create('reward_point_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->decimal('minimum_amount', 15, 4)->default(0);
            $table->decimal('per_point_amount', 15, 4)->default(1);
            $table->timestamps();
        });
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('discount_plan_customers', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('discount_plan_id');
        });
        Schema::create('discount_plans', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('discount_plan_discounts', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_plan_id');
            $table->unsignedBigInteger('discount_id');
        });
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
        });

        DB::table('units')->insert([
            'id' => 1,
            'unit_name' => 'Piece',
            'operator' => '*',
            'operation_value' => 1,
        ]);
        DB::table('general_settings')->insert(['modules' => '']);
        Cache::forget('general_setting');
    }

    public function test_search_is_scoped_to_the_selected_warehouse_and_available_stock(): void
    {
        Config::set('without_stock', 'no');
        $this->insertProduct(1, 'Oil', 'OIL-1');
        $this->insertProduct(2, 'Empty Oil', 'OIL-0');
        $this->insertProduct(3, 'Other Oil', 'OIL-2');
        DB::table('products')->where('id', 1)->update(['is_diffPrice' => true]);

        DB::table('product_warehouse')->insert([
            ['product_id' => 1, 'warehouse_id' => 1, 'qty' => 7, 'price' => 12.50],
            ['product_id' => 2, 'warehouse_id' => 1, 'qty' => 0, 'price' => 9.00],
            ['product_id' => 3, 'warehouse_id' => 2, 'qty' => 4, 'price' => 11.00],
        ]);

        $response = $this->controller()->search(1, 'oil');
        $products = $response->getData(true);

        $this->assertCount(1, $products);
        $this->assertSame('OIL-1', $products[0]['code']);
        $this->assertSame(7, (int) $products[0]['qty']);
        $this->assertSame(12.5, (float) $products[0]['price']);
    }

    public function test_product_detail_contract_keeps_delivery_fields_separate_from_extras(): void
    {
        $this->insertProduct(1, 'Oil', 'OIL-1');

        $request = Request::create('/sales/lims_product_search', 'GET', [
            'data' => [
                'code' => 'OIL-1',
                'qty' => 7,
                'pre_qty' => 2,
                'embedded' => 0,
                'batch' => '',
                'customer_id' => 1,
                'imei' => '0',
                'price' => 12.50,
            ],
        ]);

        $payload = $this->controller()->limsProductSearch($request)->getData(true);

        $this->assertSame(2, (int) $payload[15]);
        $this->assertSame(2, (int) $payload[23]);
        $this->assertNull($payload[18], 'A false serial-number flag must not become a serial number.');
        $this->assertSame('2/2', str_replace(' ', '', $payload[24]));
        $this->assertArrayNotHasKey(25, $payload);
    }

    public function test_selected_product_payload_creates_a_sale_and_deducts_delivered_stock(): void
    {
        $this->insertProduct(1, 'Oil', 'OIL-1');
        DB::table('customers')->insert(['id' => 10, 'name' => 'Customer']);
        DB::table('product_warehouse')->insert([
            'product_id' => 1,
            'warehouse_id' => 1,
            'qty' => 7,
            'price' => 12.50,
        ]);

        $request = StoreSaleRequest::create('/sales', 'POST', [
            'reference_no' => 'sale-selection-test',
            'customer_id' => 10,
            'warehouse_id' => 1,
            'biller_id' => 1,
            'item' => 1,
            'total_qty' => 2,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_price' => 25,
            'order_tax_rate' => 0,
            'order_tax' => 0,
            'order_discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 25,
            'sale_status' => 1,
            'payment_status' => 1,
            'paid_amount' => 0,
            'product_id' => [1],
            'product_batch_id' => [''],
            'product_code' => ['OIL-1'],
            'imei_number' => [''],
            'qty' => [2],
            'dqty' => [2],
            'dstatus' => ['2 / 2'],
            'sale_unit' => ['Piece'],
            'net_unit_price' => [12.5],
            'discount' => [0],
            'tax_rate' => [0],
            'tax' => [0],
            'subtotal' => [25],
        ]);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);

        $saleId = $this->controller()->store($request);

        $this->assertIsInt($saleId);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'customer_id' => 10,
            'warehouse_id' => 1,
            'total_qty' => 2,
            'grand_total' => 25,
        ]);
        $this->assertDatabaseHas('product_sales', [
            'sale_id' => $saleId,
            'product_id' => 1,
            'qty' => 2,
            'dqty' => 2,
        ]);
        $this->assertSame(5.0, (float) DB::table('product_warehouse')->where('product_id', 1)->value('qty'));
    }

    public function test_fractional_order_and_delivery_quantities_are_preserved(): void
    {
        $this->insertProduct(1, 'Oil', 'OIL-1');
        DB::table('customers')->insert(['id' => 10, 'name' => 'Customer']);
        DB::table('product_warehouse')->insert(['product_id' => 1, 'warehouse_id' => 1, 'qty' => 7, 'price' => 12.5]);
        $saleId = $this->controller()->store($this->stockRequest(0.5, 0.25));
        $this->assertIsInt($saleId);
        $this->assertDatabaseHas('product_sales', ['sale_id' => $saleId, 'qty' => 0.5, 'dqty' => 0.25]);
        $this->assertSame(6.75, (float) DB::table('product_warehouse')->where('product_id', 1)->value('qty'));
    }

    public function test_insufficient_warehouse_stock_rolls_back_sale_and_global_quantity(): void
    {
        $this->insertProduct(1, 'Oil', 'OIL-1');
        DB::table('customers')->insert(['id' => 10, 'name' => 'Customer']);
        DB::table('product_warehouse')->insert(['product_id' => 1, 'warehouse_id' => 1, 'qty' => 1, 'price' => 12.5]);
        $request = $this->stockRequest(2, 2);
        $request->headers->set('Accept', 'application/json');
        $result = $this->controller()->store($request);
        $this->assertSame(422, $result->getStatusCode());
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(20.0, (float) DB::table('products')->where('id', 1)->value('qty'));
        $this->assertSame(1.0, (float) DB::table('product_warehouse')->where('product_id', 1)->value('qty'));
    }

    public function test_digital_product_does_not_require_physical_warehouse_stock(): void
    {
        $this->insertProduct(1, 'Download', 'OIL-1');
        DB::table('products')->where('id', 1)->update(['type' => 'digital']);
        DB::table('customers')->insert(['id' => 10, 'name' => 'Customer']);
        $saleId = $this->controller()->store($this->stockRequest(1, 1));
        $this->assertIsInt($saleId);
        $this->assertSame(20.0, (float) DB::table('products')->where('id', 1)->value('qty'));
        $this->assertDatabaseHas('product_sales', ['sale_id' => $saleId, 'qty' => 1]);
    }

    public function test_invalid_unit_conversion_returns_validation_error_without_saving_sale(): void
    {
        $this->insertProduct(1, 'Oil', 'OIL-1');
        DB::table('customers')->insert(['id' => 10, 'name' => 'Customer']);
        DB::table('units')->where('id', 1)->update(['operator' => '/', 'operation_value' => 0]);
        $request = $this->stockRequest(1, 1);
        $request->headers->set('Accept', 'application/json');
        $response = $this->controller()->store($request);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(20.0, (float) DB::table('products')->where('id', 1)->value('qty'));
    }

    public function test_edit_replaces_fractional_delivery_without_changing_unrelated_stock(): void
    {
        $this->insertProduct(1, 'Oil', 'OIL-1');
        DB::table('customers')->insert(['id' => 10, 'name' => 'Customer']);
        DB::table('product_warehouse')->insert(['product_id' => 1, 'warehouse_id' => 1, 'qty' => 7]);
        $id = $this->controller()->store($this->stockRequest(0.5, 0.25));
        $this->controller()->update($this->stockRequest(0.75, 0.5), $id);
        $this->assertDatabaseHas('product_sales', ['sale_id' => $id, 'qty' => 0.75, 'dqty' => 0.5]);
        $this->assertSame(6.5, (float) DB::table('product_warehouse')->value('qty'));
        $this->assertSame(19.5, (float) DB::table('products')->where('id', 1)->value('qty'));
    }

    public function test_overselling_edit_keeps_the_previous_sale_lines_and_stock(): void
    {
        $this->insertProduct(1, 'Oil', 'OIL-1');
        DB::table('customers')->insert(['id' => 10, 'name' => 'Customer']);
        DB::table('product_warehouse')->insert(['product_id' => 1, 'warehouse_id' => 1, 'qty' => 7]);
        $id = $this->controller()->store($this->stockRequest(0.5, 0.25));
        $this->controller()->update($this->stockRequest(8, 8), $id);
        $this->assertDatabaseHas('product_sales', ['sale_id' => $id, 'qty' => 0.5, 'dqty' => 0.25]);
        $this->assertSame(6.75, (float) DB::table('product_warehouse')->value('qty'));
        $this->assertSame(19.75, (float) DB::table('products')->where('id', 1)->value('qty'));
    }

    private function stockRequest(float $qty, float $delivered): StoreSaleRequest
    {
        $request = StoreSaleRequest::create('/sales', 'POST', [
            'reference_no' => 'fractional-stock-test', 'customer_id' => 10, 'warehouse_id' => 1, 'biller_id' => 1,
            'item' => 1, 'total_qty' => $qty, 'total_discount' => 0, 'total_tax' => 0,
            'total_price' => 12.5 * $qty, 'order_tax_rate' => 0, 'order_tax' => 0,
            'order_discount' => 0, 'shipping_cost' => 0, 'grand_total' => 12.5 * $qty,
            'sale_status' => 1, 'payment_status' => 1, 'paid_amount' => 0,
            'product_id' => [1], 'product_batch_id' => [''], 'product_code' => ['OIL-1'], 'imei_number' => [''],
            'qty' => [$qty], 'dqty' => [$delivered], 'dstatus' => [$delivered.' / '.$qty], 'sale_unit' => ['Piece'],
            'net_unit_price' => [12.5], 'discount' => [0], 'tax_rate' => [0], 'tax' => [0], 'subtotal' => [12.5 * $qty],
        ]);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        return $request;
    }

    private function insertProduct(int $id, string $name, string $code): void
    {
        DB::table('products')->insert([
            'id' => $id,
            'name' => $name,
            'code' => $code,
            'type' => 'standard',
            'unit_id' => 1,
            'sale_unit_id' => 1,
            'qty' => 20,
            'price' => 10,
            'wholesale_price' => 8,
            'cost' => 5,
            'tax_method' => 1,
            'is_active' => true,
            'is_imei' => false,
            'is_diffPrice' => false,
            'is_variant' => false,
            'is_embeded' => false,
            'is_batch' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function controller(): SaleController
    {
        return $this->app->make(SaleController::class);
    }
}
