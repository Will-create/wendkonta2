<?php

namespace Tests\Feature;

use App\Http\Controllers\PurchaseProductController;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuickProductTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); config(['addons' => '', 'without_stock' => 'no']);
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('permission_id'); });
        $role = Role::create(['name' => 'Purchaser', 'guard_name' => 'web']);
        foreach (['purchases-add','products-add','products-edit'] as $name) Permission::create(['name' => $name, 'guard_name' => 'web']);
        $role->givePermissionTo('purchases-add', 'products-add');
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('code'); $t->string('type'); $t->boolean('is_active');
            foreach (['barcode_symbology','product_details','variant_option','variant_value','image','starting_date','last_date'] as $field) $t->text($field)->nullable();
            foreach (['cost','price','profit_margin','qty'] as $field) $t->decimal($field, 14, 4)->default(0);
            foreach (['unit_id','category_id','purchase_unit_id','sale_unit_id','tax_method','tax_id'] as $field) $t->integer($field)->nullable();
            $t->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $t) { $t->increments('id'); $t->integer('product_id'); $t->string('item_code'); });
        foreach (['categories','units','taxes'] as $name) Schema::create($name, function (Blueprint $t) { $t->increments('id'); $t->boolean('is_active')->default(true); });
        Schema::create('custom_fields', function (Blueprint $t) { $t->increments('id'); $t->string('belongs_to'); $t->string('name'); $t->string('type'); $t->boolean('is_admin'); $t->boolean('is_required'); });
        DB::table('categories')->insert(['id' => 1]); DB::table('units')->insert(['id' => 1]);
    }

    private function request(string $code): Request
    {
        $request = Request::create('/purchase-products', 'POST', ['name' => 'New product', 'code' => $code,
            'category_id' => 1, 'unit_id' => 1, 'cost' => '2.25', 'price' => '4.50', 'tax_method' => 1]);
        $request->setUserResolver(fn () => new GenericUser(['id' => 1, 'role_id' => 1]));
        return $request;
    }

    public function test_manually_edited_code_is_saved_by_the_existing_creation_path(): void
    {
        $response = app(PurchaseProductController::class)->store($this->request('MANUAL-CODE'));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('MANUAL-CODE', $response->getData()->code);
        $this->assertSame('MANUAL-CODE', DB::table('products')->value('code'));
        $this->assertEquals(0, DB::table('products')->value('qty'));
    }

    public function test_existing_product_and_variant_codes_are_rejected_before_saving(): void
    {
        DB::table('products')->insert(['name' => 'Old', 'code' => 'TAKEN', 'type' => 'standard', 'is_active' => false]);
        DB::table('product_variants')->insert(['product_id' => 1, 'item_code' => 'VARIANT']);
        foreach (['TAKEN','VARIANT'] as $code) {
            try { app(PurchaseProductController::class)->store($this->request($code)); $this->fail('Duplicate code was accepted'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('code', $e->errors()); }
        }
        $this->assertSame(1, DB::table('products')->count());
    }
}
