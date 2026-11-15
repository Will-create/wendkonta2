<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseDefaults
{
    public function forWarehouses(Collection $warehouses): array
    {
        $settings = DB::table('purchase_defaults')->where('id', 1)->first();
        return [
            'supplier_id' => $settings && Supplier::whereKey($settings->supplier_id)->where('is_active', true)->exists()
                ? $settings->supplier_id : null,
            'warehouse_id' => $settings && $warehouses->contains(fn ($warehouse) => (int) $warehouse->id === (int) $settings->warehouse_id && $warehouse->is_active)
                ? $settings->warehouse_id : null,
        ];
    }
}
