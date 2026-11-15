<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_defaults', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('warehouse_id')->nullable();
            $table->timestamps();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
        });
        // Reuse even an inactive Stock supplier; never duplicate or reassign history.
        $stock = DB::table('suppliers')->whereRaw('LOWER(TRIM(name)) = ?', ['stock'])->orderBy('id')->first();
        $id = $stock?->id ?? DB::table('suppliers')->insertGetId([
            'name' => 'Stock', 'company_name' => 'Stock', 'email' => '',
            'phone_number' => '', 'address' => '', 'city' => '', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($stock && !$stock->is_active) {
            DB::table('suppliers')->where('id', $id)->update(['is_active' => true, 'updated_at' => now()]);
        }
        DB::table('purchase_defaults')->insert(['id' => 1, 'supplier_id' => $id,
            'warehouse_id' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_defaults');
        // A supplier may now be referenced by purchases; retain it on rollback.
    }
};
