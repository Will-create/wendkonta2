<?php

namespace App\Http\Controllers;

use App\Models\CustomField;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class PurchaseProductController extends Controller
{
    private function permit(Request $request, string $permission): void
    {
        abort_unless(Role::find($request->user()->role_id)?->hasPermissionTo($permission), 403);
    }

    public function search(Request $request)
    {
        $this->permit($request, 'purchases-add');
        $data = $request->validate(['q' => 'required|string|min:2|max:100']);
        $query = Product::ActiveStandard()->leftJoin('product_variants as pv', 'products.id', '=', 'pv.product_id')
            ->where(function ($query) use ($data) {
                $query->where('products.name', 'like', '%'.$data['q'].'%')
                    ->orWhere('products.code', 'like', '%'.$data['q'].'%')
                    ->orWhere('pv.item_code', 'like', '%'.$data['q'].'%');
            })->selectRaw('products.id, products.name, COALESCE(pv.item_code, products.code) as code')
            ->orderBy('products.name')->orderBy('products.id')->orderBy('pv.id');
        $products = $query->limit(31)->get();
        return response()->json(['products' => $products->take(30)->values(), 'more' => $products->count() > 30]);
    }

    public function store(Request $request)
    {
        $this->permit($request, 'purchases-add');
        $this->permit($request, 'products-add');
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('products')->where('is_active', 1)],
            'code' => ['required', 'alpha_dash', 'max:100', Rule::unique('products'), function ($attribute, $value, $fail) {
                if (DB::table('product_variants')->where('item_code', $value)->exists()) $fail(__('validation.unique', ['attribute' => 'code']));
            }],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', 1)],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', 1)],
            'cost' => 'required|numeric|min:0|max:999999999',
            'price' => 'required|numeric|min:0|max:999999999',
            'tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')->where('is_active', 1)],
            'tax_method' => 'required|in:1,2',
        ];
        foreach (CustomField::where('belongs_to', 'product')->get() as $field) {
            if ($field->is_admin && (int) $request->user()->role_id !== 1) continue;
            $name = str_replace(' ', '_', strtolower($field->name));
            $rules[$name] = ($field->is_required ? 'required|' : 'nullable|').
                (in_array($field->type, ['checkbox', 'multi_select']) ? 'array' : ($field->type === 'number' ? 'numeric' : 'string|max:2000'));
        }
        $data = $request->validate($rules);
        return DB::transaction(function () use ($request, $data) {
            if (in_array('restaurant', explode(',', (string) config('addons')))) $data['menu_type'] = [];
            // Reuse the ordinary creation path, hooks, cache invalidation and flags.
            $request->replace($data + [
                'type' => 'standard', 'barcode_symbology' => 'C128', 'qty' => 0,
                'sale_unit_id' => $data['unit_id'], 'purchase_unit_id' => $data['unit_id'],
                'product_details' => '', 'starting_date' => null, 'last_date' => null,
                'profit_margin' => (float) $data['cost'] > 0 ? (((float) $data['price'] - (float) $data['cost']) / (float) $data['cost']) * 100 : 0,
            ]);
            $request->attributes->set('salepro.quick_product', true);
            $product = app(ProductController::class)->store($request);
            return response()->json($product->only('id', 'name', 'code'), 201);
        });
    }
}
