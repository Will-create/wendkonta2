<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    public function __construct(private AccountMoney $money) {}

    public function sales(int $customerId)
    {
        return Sale::where('customer_id', $customerId)->where('sale_status', 1);
    }

    public function due($sale): int
    {
        return max(0, $this->money->units($sale->grand_total) - $this->money->units($sale->paid_amount));
    }

    public function summary(Customer $customer): array
    {
        return $this->summaries(collect([$customer]))->get($customer->id);
    }

    public function summaries(\Illuminate\Support\Collection $customers): \Illuminate\Support\Collection
    {
        $sales = Sale::whereIn('customer_id', $customers->pluck('id'))->where('sale_status', 1)
            ->get(['id','customer_id','reference_no','grand_total','paid_amount','currency_id','exchange_rate','created_at'])->groupBy('customer_id');
        $deposits = Deposit::whereIn('customer_id', $customers->pluck('id'))->whereNull('reversed_at')
            ->get(['id','customer_id','amount'])->groupBy('customer_id');
        $paid = DB::table('payments')->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->whereIn('sales.customer_id', $customers->pluck('id'))->whereNull('sales.deleted_at')->where('sales.sale_status', 1)
            ->selectRaw('payments.sale_id, payments.paying_method, SUM(payments.amount) as amount, COUNT(*) as records')
            ->groupBy('payments.sale_id', 'payments.paying_method')->get()->groupBy('sale_id');
        return $customers->mapWithKeys(function ($customer) use ($sales, $deposits, $paid) {
            $depositTotal = ($deposits[$customer->id] ?? collect())->sum(fn ($deposit) => $this->money->units($deposit->amount));
            $spent = 0; $directPaid = 0; $totalSales = 0; $due = 0; $overpaid = 0; $unpaid = 0; $issues = [];
            $foreign = collect(); $invoicePaid = 0;
            foreach ($sales[$customer->id] ?? [] as $sale) {
                if (!$this->baseCurrency($sale)) { $foreign->push($sale); continue; }
                $payments = $paid[$sale->id] ?? collect();
                $actual = $payments->sum(fn ($payment) => $this->money->units($payment->amount));
                $transfers = $payments->filter(fn ($payment) => strcasecmp(trim((string) $payment->paying_method), 'Deposit') === 0)
                    ->sum(fn ($payment) => $this->money->units($payment->amount));
                $total = $this->money->units($sale->grand_total);
                if ($actual !== $this->money->units($sale->paid_amount)) $issues[] = ['type' => 'invoice_payment_mismatch', 'sale_id' => $sale->id, 'reference' => $sale->reference_no,
                    'recorded_paid' => $this->money->decimal($this->money->units($sale->paid_amount)), 'payment_records' => $this->money->decimal($actual)];
                if ($actual > $total) $issues[] = ['type' => 'overpaid_invoice', 'sale_id' => $sale->id, 'reference' => $sale->reference_no];
                $remaining = max(0, $total - $actual);
                $due += $remaining; $unpaid += $remaining > 0 ? 1 : 0;
                $overpaid += max(0, $actual - $total); $invoicePaid += $actual;
                $spent += $transfers; $directPaid += $actual - $transfers; $totalSales += $total;
            }
            if ($this->money->units($customer->deposit) !== $depositTotal) $issues[] = ['type' => 'deposit_cache_mismatch'];
            if ($this->money->units($customer->expense) !== $spent) $issues[] = ['type' => 'allocation_cache_mismatch'];
            $unspent = $depositTotal - $spent;
            if ($unspent < 0) $issues[] = ['type' => 'unfunded_deposit_payments'];
            $received = $depositTotal + $directPaid;
            return [$customer->id => [
                // Keep true deposits separate from externally received invoice payments.
                // Transfer payments consume deposits and must never be counted twice.
                'deposits' => $depositTotal, 'direct_payments' => $directPaid, 'received' => $received,
                'invoice_paid' => $invoicePaid, 'allocated' => $spent,
                'credit' => max(0, $unspent) + $overpaid, 'outstanding' => $due + max(0, -$unspent),
                'balance' => $received - $totalSales, 'purchases' => $totalSales, 'unpaid_count' => $unpaid,
                'foreign_sales' => $foreign, 'reconciled' => !$issues, 'issues' => $issues,
            ]];
        });
    }

    public function baseCurrency($sale): bool
    {
        return (!$sale->currency_id || (int) $sale->currency_id === (int) config('currency_id'))
            && (!$sale->exchange_rate || (float) $sale->exchange_rate === 1.0);
    }

    public function deposit(Customer $customer, array $data, int $userId): Deposit
    {
        $amount = $this->money->input((string) $data['amount']);
        $payload = $data;
        $payload['amount'] = $this->money->decimal($amount);
        ksort($payload);
        $hash = hash('sha256', json_encode([$customer->id, $userId, $payload], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($customer, $data, $userId, $amount, $hash) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $existing = Deposit::where('receipt_token', $data['receipt_token'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, __('credit.token_conflict'));
                return $existing;
            }
            $summary = $this->summary($customer);
            if (!$summary['reconciled']) throw ValidationException::withMessages(['customer_id' => __('credit.reconcile_first')]);
            $account = DB::table('accounts')->where('id', $data['account_id'])->where('is_active', true)->lockForUpdate()->first();
            abort_unless($account, 422, __('credit.invalid_account'));
            $register = DB::table('cash_registers')->where('user_id', $userId)->where('status', true)->orderByDesc('id')->first();
            $deposit = Deposit::create([
                'customer_id' => $customer->id, 'user_id' => $userId, 'amount' => $this->money->decimal($amount),
                'note' => $data['note'] ?? null, 'receipt_token' => $data['receipt_token'], 'request_hash' => $hash,
                'transaction_date' => $data['transaction_date'], 'payment_method' => $data['payment_method'],
                'account_id' => $account->id, 'cash_register_id' => $register?->id, 'reference' => $data['reference'] ?? null,
            ]);
            $remaining = $amount;
            $sales = $this->sales($customer->id)->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($sales as $sale) {
                if (!$remaining) break;
                if (!$this->baseCurrency($sale) || $sale->created_at->gt($data['transaction_date'])) continue;
                $allocated = min($remaining, $this->due($sale));
                if (!$allocated) continue;
                $payment = new Payment([
                    'sale_id' => $sale->id, 'user_id' => $userId, 'account_id' => $account->id,
                    'cash_register_id' => $register?->id, 'payment_reference' => 'DEP-'.$deposit->id.'-'.$sale->id,
                    'amount' => $this->money->decimal($allocated), 'change' => 0, 'paying_method' => 'Deposit',
                    'payment_note' => 'DEP-'.$deposit->id,
                ]);
                $payment->skipCustomerCreditAllocation = true;
                $payment->save();
                DB::table('payments')->where('id', $payment->id)->update(['created_at' => $data['transaction_date'], 'customer_credit_transfer' => $this->money->decimal($allocated)]);
                DB::table('customer_deposit_allocations')->insert([
                    'deposit_id' => $deposit->id, 'sale_id' => $sale->id, 'payment_id' => $payment->id,
                    'amount' => $this->money->decimal($allocated), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $sale->paid_amount = $this->money->decimal($this->money->units($sale->paid_amount) + $allocated);
                $sale->payment_status = $this->due($sale) ? 2 : 4;
                $sale->save();
                $remaining -= $allocated;
            }
            $customer->deposit = $this->money->decimal($summary['deposits'] + $amount);
            $customer->expense = $this->money->decimal($summary['allocated'] + $amount - $remaining);
            $customer->save();
            cache()->forget('customer_list');
            return $deposit;
        }, 3);
    }

    public function reverse(Deposit $deposit, string $note, int $userId): Deposit
    {
        return DB::transaction(function () use ($deposit, $note, $userId) {
            $customer = Customer::whereKey($deposit->customer_id)->lockForUpdate()->firstOrFail();
            $deposit = Deposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            abort_unless($deposit->receipt_token, 422, __('credit.legacy_deposit'));
            if ($deposit->reversed_at) return $deposit;
            $allocations = DB::table('customer_deposit_allocations')->where('deposit_id', $deposit->id)->orderBy('sale_id')->get();
            $sum = 0;
            foreach ($allocations as $allocation) $sum += $this->money->units($allocation->amount);
            $summary = $this->summary($customer);
            // Excess may already have been spent by the ordinary POS Deposit method.
            if (!$summary['reconciled'] || $summary['credit'] + $sum < $this->money->units($deposit->amount)) {
                throw ValidationException::withMessages(['deposit' => __('credit.credit_spent')]);
            }
            foreach ($allocations as $allocation) {
                $sale = Sale::whereKey($allocation->sale_id)->lockForUpdate()->firstOrFail();
                $amount = $this->money->units($allocation->amount);
                abort_if($this->money->units($sale->paid_amount) < $amount, 409, __('credit.reconcile_first'));
                $payment = new Payment([
                    'sale_id' => $sale->id, 'user_id' => $userId, 'account_id' => $deposit->account_id,
                    'payment_reference' => 'REV-DEP-'.$deposit->id.'-'.$sale->id,
                    'amount' => $this->money->decimal(-$amount), 'change' => 0, 'paying_method' => 'Deposit',
                    'payment_note' => $note,
                ]);
                $payment->skipCustomerCreditAllocation = true;
                $payment->save();
                DB::table('payments')->where('id', $payment->id)->update(['customer_credit_transfer' => $this->money->decimal(-$amount)]);
                DB::table('customer_deposit_allocations')->where('id', $allocation->id)->update(['reversal_payment_id' => $payment->id, 'updated_at' => now()]);
                $sale->paid_amount = $this->money->decimal($this->money->units($sale->paid_amount) - $amount);
                $sale->payment_status = $this->money->units($sale->paid_amount) > 0 ? 2 : 1;
                $sale->save();
            }
            $register = DB::table('cash_registers')->where('user_id', $userId)->where('status', true)->orderByDesc('id')->first();
            $deposit->forceFill(['reversed_at' => now(), 'reversed_by' => $userId, 'reversal_note' => $note,
                'reversal_cash_register_id' => $register?->id])->save();
            $customer->deposit = $this->money->decimal($summary['deposits'] - $this->money->units($deposit->amount));
            $customer->expense = $this->money->decimal($summary['allocated'] - $sum);
            $customer->save();
            cache()->forget('customer_list');
            return $deposit;
        }, 3);
    }
}
