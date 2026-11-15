<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductListing
{
    public function warehouse(Request $request): int
    {
        $warehouse = max(0, (int) $request->input('warehouse_id', 0));
        $user = $request->user();
        if ($user && $user->role_id > 2) {
            abort_unless($user->warehouse_id, 403);
            abort_if($warehouse && $warehouse !== (int) $user->warehouse_id, 403);
            return (int) $user->warehouse_id;
        }
        return $warehouse;
    }

    public function query(Request $request, array $customFields = [])
    {
        $stock = DB::table('product_warehouse')->select('product_id')->selectRaw('SUM(qty) as available_qty')->groupBy('product_id');
        if ($warehouse = $this->warehouse($request)) $stock->where('warehouse_id', $warehouse);
        $query = Product::where('products.is_active', true)->with('category', 'brand', 'unit')
            ->leftJoinSub($stock, 'listing_stock', fn ($join) => $join->on('listing_stock.product_id', '=', 'products.id'))
            ->select('products.*')->selectRaw("CASE WHEN products.type = 'standard' THEN COALESCE(listing_stock.available_qty, 0) ELSE products.qty END as available_qty");
        foreach (['category_id', 'brand_id', 'unit_id', 'tax_id'] as $field) {
            if ((int) $request->input($field) > 0) $query->where('products.'.$field, (int) $request->input($field));
        }
        if ($request->filled('product_type') && $request->product_type !== 'all') $query->where('products.type', $request->product_type);
        if ($request->boolean('is_recipe')) $query->where('products.is_recipe', 1);
        if ($request->imeiorvariant === 'imei') $query->where('products.is_imei', 1);
        if ($request->imeiorvariant === 'variant') $query->where('products.is_variant', 1);
        if ($request->filled('search.value')) {
            $search = '%'.$request->input('search.value').'%';
            $query->where(function ($q) use ($search, $customFields) {
                $q->where('products.name', 'like', $search)->orWhere('products.code', 'like', $search)
                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', $search))
                    ->orWhereHas('brand', fn ($q) => $q->where('title', 'like', $search))
                    ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('product_variants')->whereColumn('product_variants.product_id', 'products.id')->where('item_code', 'like', $search))
                    ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('product_purchases')->whereColumn('product_purchases.product_id', 'products.id')->where('imei_number', 'like', $search));
                foreach ($customFields as $field) $q->orWhere('products.'.$field, 'like', $search);
            });
        }
        return $query;
    }
}
