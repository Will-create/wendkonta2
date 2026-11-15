<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        $user = $request->user();
        $permissions = DB::table('permissions')->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $user->role_id)->pluck('permissions.name');
        abort_unless($permissions->intersect(['sales-index', 'purchases-index', 'products-index'])->isNotEmpty(), 403);
        $warehouse = (int) ($data['warehouse_id'] ?? 0);
        if ($user->role_id > 2 && $user->warehouse_id) {
            abort_if($warehouse && $warehouse !== (int) $user->warehouse_id, 403);
            $warehouse = (int) $user->warehouse_id;
        }
        if ($warehouse) {
            abort_unless(DB::table('warehouses')->where('id', $warehouse)->where('is_active', true)->exists(), 422, 'Select an active warehouse.');
        }
        $start = $data['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $data['end_date'] ?? now()->toDateString();
        $precision = max(0, min(6, (int) config('decimal', 2)));
        $metrics = [];
        foreach ([['sales-index', Sale::query(), 'returns', 'sale_id', 'customer_due', 'customer'],
                  ['purchases-index', Purchase::query(), 'return_purchases', 'purchase_id', 'supplier_due', 'supplier']] as $definition) {
            [$permission, $query, $returnTable, $foreignKey, $key, $url] = $definition;
            if (!$permissions->contains($permission)) {
                continue;
            }
            $table = $query->getModel()->getTable();
            $query->where($table.'.payment_status', '!=', $table === 'sales' ? 4 : 2);
            $returns = DB::table($returnTable)->select($foreignKey)->selectRaw('SUM(grand_total) AS returned')->groupBy($foreignKey);
            $query->leftJoinSub($returns, 'returned_totals', fn ($join) => $join->on($table.'.id', '=', 'returned_totals.'.$foreignKey))
                ->whereDate($table.'.created_at', '>=', $start)->whereDate($table.'.created_at', '<=', $end);
            if ($warehouse) {
                $query->where($table.'.warehouse_id', $warehouse);
            }
            if ($user->role_id > 2 && config('staff_access') === 'own') {
                $query->where($table.'.user_id', $user->id);
            }
            // Sales returns in this installation are cash payouts, not invoice payments.
            $due = $table.'.grand_total - COALESCE('.$table.'.paid_amount, 0)'.
                ($table === 'sales' ? '' : ' - COALESCE(returned_totals.returned, 0)');
            $balance = $query->sum(DB::raw('CASE WHEN ('.$due.') > 0 THEN ('.$due.') ELSE 0 END'));
            $metrics[$key] = ['value' => number_format((float) $balance, $precision, '.', ''),
                'label' => trans('mobile.'.$key), 'url' => url($url), 'type' => 'money'];
        }
        if ($permissions->contains('products-index')) {
            $quantities = DB::table('product_warehouse')->select('product_id')->selectRaw('SUM(qty) AS quantity')->groupBy('product_id');
            if ($warehouse) {
                $quantities->where('warehouse_id', $warehouse);
            }
            $stock = DB::table('products')->leftJoinSub($quantities, 'stock_totals', fn ($join) => $join->on('products.id', '=', 'stock_totals.product_id'))
                ->where('products.is_active', true)->where('products.type', 'standard');
            $units = (clone $stock)->sum(DB::raw('COALESCE(stock_totals.quantity, 0)'));
            $low = (clone $stock)->where('products.alert_quantity', '>', 0)
                ->whereRaw('COALESCE(stock_totals.quantity, 0) < products.alert_quantity')->count();
            $metrics['stock_units'] = ['value' => (string) $units, 'label' => trans('mobile.stock_units'), 'url' => url('products'), 'type' => 'quantity'];
            $metrics['low_stock'] = ['value' => (string) $low, 'label' => trans('mobile.low_stock'), 'url' => url('products'), 'type' => 'quantity'];
        }
        return response()->json(['warehouse_id' => $warehouse, 'start_date' => $start, 'end_date' => $end,
            'currency' => config('currency'), 'currency_position' => config('currency_position'),
            'precision' => $precision, 'metrics' => $metrics])->header('Cache-Control', 'private, no-store');
    }
}
