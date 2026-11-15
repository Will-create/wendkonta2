<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\AccountMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerCreditPaymentObserver
{
    public function creating(Payment $payment): void
    {
        if ($payment->skipCustomerCreditAllocation || $payment->paying_method !== 'Deposit' || !$payment->sale_id
            || !Schema::hasTable('customer_deposit_allocations')) return;
        $sale = Sale::findOrFail($payment->sale_id);
        $customer = Customer::whereKey($sale->customer_id)->lockForUpdate()->firstOrFail();
        if (!Deposit::where('customer_id', $customer->id)->whereNotNull('receipt_token')->exists()) return;
        $money = app(AccountMoney::class);
        if (!app(\App\Services\CustomerAccountService::class)->baseCurrency($sale)
            || $money->units($payment->amount) <= 0
            || $money->units($payment->amount) > $money->units($customer->deposit) - $money->units($customer->expense)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => __('credit.credit_spent')]);
        }
    }

    public function updating(Payment $payment): void
    {
        if ($payment->isDirty() && $this->managed($payment)) {
            abort(422, __('credit.immutable_receipt'));
        }
    }

    public function deleting(Payment $payment): void
    {
        if ($this->managed($payment)) abort(422, __('credit.immutable_receipt'));
    }

    private function managed(Payment $payment): bool
    {
        return Schema::hasTable('customer_deposit_allocations') && DB::table('customer_deposit_allocations')
            ->where('payment_id', $payment->id)->orWhere('reversal_payment_id', $payment->id)->exists();
    }

    public function created(Payment $payment): void
    {
        if ($payment->skipCustomerCreditAllocation || $payment->paying_method !== 'Deposit'
            || !$payment->sale_id || (float) $payment->amount <= 0
            || !Schema::hasTable('customer_deposit_allocations')) return;
        // Normal POS credit spending shares the same receipt/allocation trail.
        DB::transaction(function () use ($payment) {
            $sale = Sale::findOrFail($payment->sale_id);
            Customer::whereKey($sale->customer_id)->lockForUpdate()->firstOrFail();
            $money = app(AccountMoney::class);
            $remaining = $money->units($payment->amount);
            $transferred = 0;
            $deposits = Deposit::where('customer_id', $sale->customer_id)->whereNotNull('receipt_token')
                ->whereNull('reversed_at')->orderBy('transaction_date')->orderBy('id')->lockForUpdate()->get();
            foreach ($deposits as $deposit) {
                $used = DB::table('customer_deposit_allocations')->where('deposit_id', $deposit->id)
                    ->whereNull('reversal_payment_id')->sum('amount');
                $amount = min($remaining, max(0, $money->units($deposit->amount) - $money->units($used)));
                if (!$amount) continue;
                DB::table('customer_deposit_allocations')->insert([
                    'deposit_id' => $deposit->id, 'sale_id' => $sale->id, 'payment_id' => $payment->id,
                    'amount' => $money->decimal($amount), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $remaining -= $amount; $transferred += $amount;
                if (!$remaining) break;
            }
            DB::table('payments')->where('id', $payment->id)->update(['customer_credit_transfer' => $money->decimal($transferred)]);
        });
    }
}
