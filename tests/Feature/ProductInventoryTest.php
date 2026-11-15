<?php

namespace Tests\Feature;

use App\Services\ProductCode;
use App\Services\ProductListing;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductInventoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('code'); $t->string('type')->default('standard');
            $t->boolean('is_active')->default(true); $t->integer('category_id'); $t->integer('brand_id')->nullable();
            $t->integer('unit_id')->nullable(); $t->integer('tax_id')->nullable(); $t->integer('is_recipe')->default(0);
            $t->integer('is_imei')->default(0); $t->integer('is_variant')->default(0); $t->decimal('qty', 14, 4)->default(999);
        });
        Schema::create('product_warehouse', function (Blueprint $t) { $t->increments('id'); $t->integer('product_id'); $t->integer('warehouse_id'); $t->decimal('qty', 14, 4); });
        Schema::create('categories', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('brands', function (Blueprint $t) { $t->increments('id'); $t->string('title'); });
        Schema::create('units', function (Blueprint $t) { $t->increments('id'); $t->string('unit_name'); });
        Schema::create('product_variants', function (Blueprint $t) { $t->increments('id'); $t->integer('product_id'); $t->string('item_code'); });
        Schema::create('product_purchases', function (Blueprint $t) { $t->increments('id'); $t->integer('product_id'); $t->string('imei_number'); });
        DB::table('categories')->insert([['id' => 1, 'name' => 'Food'], ['id' => 2, 'name' => 'Tools']]);
        DB::table('products')->insert([
            ['id' => 1, 'name' => 'Rice', 'code' => 'RICE', 'category_id' => 1, 'is_active' => true],
            ['id' => 2, 'name' => 'Rice cooker', 'code' => 'COOK', 'category_id' => 2, 'is_active' => true],
            ['id' => 3, 'name' => 'Hidden rice', 'code' => 'HIDDEN', 'category_id' => 1, 'is_active' => false],
        ]);
        DB::table('product_warehouse')->insert([
            ['product_id' => 1, 'warehouse_id' => 1, 'qty' => 2.5], ['product_id' => 1, 'warehouse_id' => 1, 'qty' => 3],
            ['product_id' => 1, 'warehouse_id' => 2, 'qty' => 10], ['product_id' => 2, 'warehouse_id' => 1, 'qty' => 8],
        ]);
    }

    public function test_category_search_and_warehouse_quantities_share_the_filtered_query(): void
    {
        $request = Request::create('/products/product-data', 'POST', ['category_id' => 1, 'warehouse_id' => 1, 'search' => ['value' => 'rice']]);
        $query = app(ProductListing::class)->query($request);
        $this->assertSame(1, (clone $query)->count());
        $product = $query->first();
        $this->assertSame(1, $product->id); $this->assertEquals(5.5, $product->available_qty);
        $request->merge(['category_id' => 0, 'warehouse_id' => 0]);
        $query = app(ProductListing::class)->query($request);
        $this->assertSame(2, (clone $query)->count());
        $this->assertEquals(15.5, $query->where('products.id', 1)->first()->available_qty);
    }

    public function test_pagination_does_not_drop_matches_or_duplicate_variant_and_purchase_rows(): void
    {
        for ($i = 0; $i < 4; ++$i) DB::table('product_purchases')->insert(['product_id' => 1, 'imei_number' => 'MATCH-'.$i]);
        DB::table('product_variants')->insert(['product_id' => 1, 'item_code' => 'MATCH-VARIANT']);
        $request = Request::create('/', 'POST', ['search' => ['value' => 'MATCH']]);
        $query = app(ProductListing::class)->query($request);
        $this->assertSame(1, (clone $query)->count());
        $this->assertCount(1, $query->offset(0)->limit(1)->get());
        $request->merge(['search' => ['value' => 'rice'], 'category_id' => 0]);
        $query = app(ProductListing::class)->query($request);
        $this->assertSame(2, (clone $query)->count());
        $this->assertSame(2, $query->orderBy('products.id')->offset(1)->limit(1)->first()->id);
    }

    public function test_staff_cannot_request_other_warehouse_stock(): void
    {
        $request = Request::create('/', 'POST', ['warehouse_id' => 2]);
        $request->setUserResolver(fn () => new GenericUser(['id' => 1, 'role_id' => 3, 'warehouse_id' => 1]));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ProductListing::class)->query($request);
    }

    public function test_generated_code_uses_existing_eight_digit_convention_and_is_not_an_existing_product_or_variant(): void
    {
        $service = app(ProductCode::class); $seen = [];
        for ($i = 0; $i < 10; ++$i) {
            $code = $service->generate(); $this->assertMatchesRegularExpression('/^\d{8}$/', $code);
            $this->assertNotContains($code, $seen); $seen[] = $code;
            DB::table('product_variants')->insert(['product_id' => 1, 'item_code' => $code]);
        }
    }
}
