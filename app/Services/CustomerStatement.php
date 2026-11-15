<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerStatement
{
    public function __construct(private AccountMoney $money) {}

    public function query(Customer $customer)
    {
        $base = fn ($query) => $query->where(function ($q) {
            $q->whereNull('s.currency_id')->orWhere('s.currency_id', config('currency_id'));
        })->where(function ($q) { $q->whereNull('s.exchange_rate')->orWhere('s.exchange_rate', 1); });
        $sales = $base(DB::table('sales as s')->where('s.customer_id', $customer->id)->whereNull('s.deleted_at')->where('s.sale_status', 1))
            ->selectRaw("s.created_at as date, 1 as sort, s.id, 'sale' as type, s.reference_no as reference, s.sale_note as note, -s.grand_total as delta, s.grand_total as amount, s.user_id, s.reference_no as sale_reference, NULL as payment_method");
        $payments = $base(DB::table('payments as p')->join('sales as s', 's.id', '=', 'p.sale_id')->where('s.customer_id', $customer->id)->whereNull('s.deleted_at')->where('s.sale_status', 1))
            ->selectRaw("p.created_at as date, 5 as sort, p.id, CASE WHEN LOWER(TRIM(p.paying_method)) = 'deposit' THEN 'allocation' ELSE 'payment' END as type, p.payment_reference as reference, p.payment_note as note, CASE WHEN LOWER(TRIM(p.paying_method)) = 'deposit' THEN 0 ELSE p.amount END as delta, p.amount, p.user_id, s.reference_no as sale_reference, p.paying_method as payment_method");
        $deposits = DB::table('deposits as d')->where('d.customer_id', $customer->id)
            ->selectRaw("COALESCE(d.transaction_date, d.created_at) as date, 2 as sort, d.id, 'deposit' as type, COALESCE(d.reference, '') as reference, d.note, d.amount as delta, d.amount, d.user_id, NULL as sale_reference, d.payment_method");
        $reversals = DB::table('deposits as d')->where('d.customer_id', $customer->id)->whereNotNull('d.reversed_at')
            ->selectRaw("d.reversed_at as date, 6 as sort, d.id, 'reversal' as type, COALESCE(d.reference, '') as reference, d.reversal_note as note, -d.amount as delta, d.amount, d.reversed_by as user_id, NULL as sale_reference, d.payment_method");
        $returns = $base(DB::table('returns as r')->join('sales as s', 'r.sale_id', '=', 's.id')->where('s.customer_id', $customer->id)->whereNull('s.deleted_at')->where('s.sale_status', 1))
            ->selectRaw("r.created_at as date, 3 as sort, r.id, 'return' as type, r.reference_no as reference, r.return_note as note, r.grand_total as delta, r.grand_total as amount, r.user_id, s.reference_no as sale_reference, NULL as payment_method");
        $refunds = $base(DB::table('returns as r')->join('sales as s', 'r.sale_id', '=', 's.id')->where('s.customer_id', $customer->id)->whereNull('s.deleted_at')->where('s.sale_status', 1))
            ->selectRaw("r.created_at as date, 4 as sort, r.id, 'refund' as type, r.reference_no as reference, r.return_note as note, -r.grand_total as delta, r.grand_total as amount, r.user_id, s.reference_no as sale_reference, NULL as payment_method");
        // Existing SalePro returns are cash refunds. The credit note and payout
        // offset each other; they never also pay an unpaid invoice.
        return DB::query()->fromSub($sales->unionAll($deposits)->unionAll($returns)->unionAll($refunds)->unionAll($payments)->unionAll($reversals), 'activity')
            ->leftJoin('users', 'activity.user_id', '=', 'users.id')->select('activity.*', 'users.name as responsible')
            ->orderBy('date')->orderBy('sort')->orderBy('activity.id');
    }

    public function report(Customer $customer, array $filters, bool $all = false): array
    {
        $balance = 0; $opening = 0; $count = 0; $rows = []; $totals = ['sale' => 0, 'deposit' => 0, 'payment' => 0, 'allocation' => 0, 'return' => 0, 'refund' => 0, 'reversal' => 0];
        $page = max(1, (int) ($filters['page'] ?? 1));
        foreach ($this->query($customer)->cursor() as $row) {
            $date = substr($row->date, 0, 10);
            if (!empty($filters['end_date']) && $date > $filters['end_date']) break;
            $balance += $this->money->units($row->delta);
            if (!empty($filters['start_date']) && $date < $filters['start_date']) { $opening = $balance; continue; }
            $totals[$row->type] += $this->money->units($row->amount);
            if (!empty($filters['type']) && $row->type !== $filters['type']) continue;
            $row->balance = $balance;
            if ($all || ($count >= ($page - 1) * 30 && $count < $page * 30)) $rows[] = $row;
            ++$count;
        }
        $managed = DB::table('deposits')->whereIn('id', collect($rows)->whereIn('type', ['deposit','reversal'])->pluck('id'))
            ->whereNotNull('receipt_token')->pluck('id')->all();
        foreach ($rows as $row) $row->managed_receipt = in_array((int) $row->id, array_map('intval', $managed));
        return ['opening' => $opening, 'closing' => $balance, 'totals' => $totals,
            'rows' => $all ? collect($rows) : new LengthAwarePaginator($rows, $count, 30, $page, ['path' => request()->url(), 'query' => request()->except('page')])];
    }
}
