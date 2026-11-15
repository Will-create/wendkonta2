<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseDefaultsController extends Controller
{
    private function authorizeSettings(Request $request): void
    {
        abort_unless((int) $request->user()->role_id === 1, 403);
    }

    public function edit(Request $request)
    {
        $this->authorizeSettings($request);
        return view('backend.purchase.defaults', [
            'defaults' => DB::table('purchase_defaults')->where('id', 1)->first(),
            'warehouses' => Warehouse::where('is_active', true)->get(),
            'suppliers' => Supplier::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeSettings($request);
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
        ]);
        DB::table('purchase_defaults')->where('id', 1)->update($data + ['updated_at' => now()]);
        return back()->with('message', __('mobile.saved'));
    }
}
