<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Product_Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComboStockService
{
    // Positive quantities restore components; negative quantities consume them.
    public function adjust(Product $combo, int $warehouse, float $quantity): void
    {
        DB::transaction(function () use ($combo, $warehouse, $quantity) {
            $products = explode(',', $combo->product_list ?? '');
            $variants = explode(',', $combo->variant_list ?? '');
            $quantities = explode(',', $combo->qty_list ?? '');
            if (!is_finite($quantity) || count($products) !== count($quantities)) {
                throw ValidationException::withMessages(['qty' => 'Review the combo components and quantities.']);
            }
            Product::whereIn('id', $products)->orderBy('id')->lockForUpdate()->get();
            Product_Warehouse::whereIn('product_id', $products)->orderBy('id')->lockForUpdate()->get();
            foreach ($products as $index => $id) {
                $component = Product::find($id);
                $multiplier = (float) ($quantities[$index] ?? 0);
                if (!$component || !is_finite($multiplier) || $multiplier <= 0) {
                    throw ValidationException::withMessages(['qty' => 'Review the combo components and quantities.']);
                }
                $delta = $quantity * $multiplier;
                $variant = !empty($variants[$index]) ? ProductVariant::where('product_id', $id)->where('variant_id', $variants[$index])->first() : null;
                $stock = Product_Warehouse::where('product_id', $id)->where('warehouse_id', $warehouse);
                if (!empty($variants[$index])) {
                    if (!$variant) throw ValidationException::withMessages(['qty' => 'A combo component variant is unavailable.']);
                    $stock->where('variant_id', $variants[$index]);
                }
                $stock = $stock->first();
                if ($delta < 0 && !filter_var(config('without_stock'), FILTER_VALIDATE_BOOLEAN) && (float) ($stock->qty ?? 0) + 0.000001 < -$delta) {
                    throw ValidationException::withMessages(['qty' => 'Insufficient component stock for '.$combo->name]);
                }
                $component->qty += $delta;
                $component->save();
                if ($variant) {
                    $variant->qty += $delta;
                    $variant->save();
                }
                if ($stock) {
                    $stock->qty += $delta;
                    $stock->save();
                }
            }
        });
    }
}
