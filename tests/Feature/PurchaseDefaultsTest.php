<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use App\Services\PurchaseDefaults;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('suppliers', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('company_name'); $t->string('email');
            $t->string('phone_number'); $t->string('address'); $t->string('city'); $t->boolean('is_active'); $t->timestamps();
        });
        Schema::create('warehouses', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->boolean('is_active'); $t->timestamps(); });
        Warehouse::create(['name' => 'First warehouse', 'is_active' => true]);
        Warehouse::create(['name' => 'Other warehouse', 'is_active' => true]);
    }

    public function test_migration_reuses_stock_without_choosing_arbitrary_warehouse(): void
    {
        DB::table('suppliers')->insert(['name' => ' stock ', 'company_name' => 'Existing', 'email' => '', 'phone_number' => '', 'address' => '', 'city' => '', 'is_active' => false]);
        (require database_path('migrations/2026_11_15_000100_create_purchase_defaults_table.php'))->up();
        $this->assertSame(1, DB::table('suppliers')->count());
        $defaults = app(PurchaseDefaults::class)->forWarehouses(Warehouse::all());
        $this->assertSame(1, $defaults['supplier_id']); $this->assertNull($defaults['warehouse_id']);
    }

    public function test_configured_warehouse_is_only_default_when_permitted_and_active(): void
    {
        (require database_path('migrations/2026_11_15_000100_create_purchase_defaults_table.php'))->up();
        DB::table('purchase_defaults')->update(['warehouse_id' => 2]);
        $service = app(PurchaseDefaults::class);
        $this->assertSame(2, $service->forWarehouses(Warehouse::all())['warehouse_id']);
        $this->assertNull($service->forWarehouses(Warehouse::where('id', 1)->get())['warehouse_id']);
        DB::table('suppliers')->update(['is_active' => false]);
        $this->assertNull($service->forWarehouses(Warehouse::all())['supplier_id']);
    }
}
