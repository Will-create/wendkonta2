<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CustomerDepositCash
{
    public function query()
    {
        $receipts = DB::table('deposits')->whereNotNull('receipt_token')->selectRaw(
            "id, account_id, cash_register_id, payment_method, transaction_date as created_at, amount, user_id, 'deposit' as type"
        );
        $reversals = DB::table('deposits')->whereNotNull('receipt_token')->whereNotNull('reversed_at')->selectRaw(
            "id, account_id, reversal_cash_register_id as cash_register_id, payment_method, reversed_at as created_at, -amount as amount, reversed_by as user_id, 'reversal' as type"
        );
        return DB::query()->fromSub($receipts->unionAll($reversals), 'deposit_cash');
    }

    public function total(?int $account = null, ?int $register = null, ?string $method = null, ?string $start = null, ?string $end = null): float
    {
        $query = $this->query();
        if ($account !== null) $query->where('account_id', $account);
        if ($register !== null) $query->where('cash_register_id', $register);
        if ($method !== null) $query->where('payment_method', $method);
        if ($start !== null) $query->whereDate('created_at', '>=', $start);
        if ($end !== null) $query->whereDate('created_at', '<=', $end);
        return (float) $query->sum('amount');
    }
}
