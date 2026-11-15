<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductFlagCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_variant')->nullable();
            $table->boolean('is_imei')->nullable();
        });
        DB::table('products')->insert([
            ['id' => 1, 'is_variant' => null, 'is_imei' => null],
            ['id' => 2, 'is_variant' => 0, 'is_imei' => 0],
            ['id' => 3, 'is_variant' => 1, 'is_imei' => 1],
        ]);
    }

    public function test_catalog_queries_accept_null_and_zero_as_false(): void
    {
        $this->assertSame([1, 2], Product::withoutVariant()->orderBy('id')->pluck('id')->all());
        $this->assertSame([1, 2], Product::withoutSerialNumber()->orderBy('id')->pluck('id')->all());
    }

    public function test_false_flag_scope_keeps_other_query_restrictions(): void
    {
        $this->assertSame([2], Product::where('id', 2)->withoutVariant()->withoutSerialNumber()->pluck('id')->all());
    }
}
