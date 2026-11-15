<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ComboStockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComboStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['without_stock' => 'no']);
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->double('qty');
            $table->string('product_list')->nullable(); $table->string('qty_list')->nullable(); $table->string('variant_list')->nullable(); $table->timestamps();
        });
        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->id(); $table->integer('product_id'); $table->integer('warehouse_id'); $table->integer('variant_id')->nullable(); $table->double('qty'); $table->timestamps();
        });
        DB::table('products')->insert([
            ['id' => 1, 'name' => 'Oil', 'qty' => 10], ['id' => 2, 'name' => 'Flour', 'qty' => 20],
        ]);
        DB::table('products')->insert(['id' => 3, 'name' => 'Combo', 'qty' => 0, 'product_list' => '1,2', 'qty_list' => '2,0.5']);
        DB::table('product_warehouse')->insert([
            ['product_id' => 1, 'warehouse_id' => 1, 'qty' => 4], ['product_id' => 2, 'warehouse_id' => 1, 'qty' => 1],
        ]);
    }

    public function test_fractional_combo_components_are_consumed_and_restored(): void
    {
        $combo = Product::find(3);
        app(ComboStockService::class)->adjust($combo, 1, -1.5);
        $this->assertEqualsWithDelta(1, DB::table('product_warehouse')->where('product_id', 1)->value('qty'), 0.000001);
        $this->assertEqualsWithDelta(0.25, DB::table('product_warehouse')->where('product_id', 2)->value('qty'), 0.000001);
        app(ComboStockService::class)->adjust($combo, 1, 1.5);
        $this->assertSame(10.0, (float) Product::find(1)->qty);
        $this->assertSame(20.0, (float) Product::find(2)->qty);
    }

    public function test_insufficient_later_component_rolls_back_earlier_components(): void
    {
        DB::table('product_warehouse')->where('product_id', 2)->update(['qty' => 0]);
        try {
            app(ComboStockService::class)->adjust(Product::find(3), 1, -1);
            $this->fail('Insufficient component stock was accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('qty', $error->errors());
        }
        $this->assertSame(4.0, (float) DB::table('product_warehouse')->where('product_id', 1)->value('qty'));
        $this->assertSame(10.0, (float) Product::find(1)->qty);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_explicit_yes_setting_preserves_sales_without_stock(): void
    {
        config(['without_stock' => 'yes']);
        app(ComboStockService::class)->adjust(Product::find(3), 1, -3);
        $this->assertSame(-2.0, (float) DB::table('product_warehouse')->where('product_id', 1)->value('qty'));
    }
}
