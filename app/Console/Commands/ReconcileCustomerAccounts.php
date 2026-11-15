<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\AccountMoney;
use App\Services\CustomerAccountService;
use App\Services\CustomerStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ReconcileCustomerAccounts extends Command
{
    protected $signature = 'customer-accounts:reconcile {--output=reports/customer-account-reconciliation.json}';
    protected $description = 'Reconcile customer accounts against original payments without creating or changing financial records';

    public function handle(CustomerAccountService $accounts, AccountMoney $money, CustomerStatement $statements): int
    {
        if (Schema::hasTable('general_settings') && ($settings = DB::table('general_settings')->latest('id')->first())) {
            config(['currency_id' => $settings->currency, 'decimal' => $settings->decimal]);
        }
        $report = ['generated_at' => now()->toIso8601String(), 'policy' => 'Original sales/payments/deposits projected once by source ID; no financial rows written.',
            'customers' => 0, 'review_required' => 0, 'sales' => 0, 'received' => 0, 'outstanding' => 0, 'credit' => 0, 'balance' => 0, 'exceptions' => []];
        Customer::orderBy('id')->chunkById(100, function ($customers) use ($accounts, $money, $statements, &$report) {
            $byId = $customers->keyBy('id');
            foreach ($accounts->summaries($customers) as $id => $summary) {
                ++$report['customers'];
                foreach (['received','outstanding','credit','balance'] as $key) $report[$key] += $summary[$key];
                $report['sales'] += $summary['purchases'];
                $issues = $summary['issues'];
                $ledger = $statements->report($byId[$id], []);
                if ($ledger['closing'] !== $summary['balance']) $issues[] = ['type' => 'ledger_balance_mismatch',
                    'ledger_closing' => $money->decimal($ledger['closing']), 'computed_balance' => $money->decimal($summary['balance'])];
                if ($summary['foreign_sales']->isNotEmpty()) $issues[] = ['type' => 'foreign_currency_requires_separate_reconciliation', 'sale_ids' => $summary['foreign_sales']->pluck('id')->all()];
                if ($issues) { ++$report['review_required']; $report['exceptions'][] = ['customer_id' => $id, 'issues' => $issues]; }
            }
        });
        $report['orphan_payment_ids'] = DB::table('payments as p')->leftJoin('sales as s', 's.id', '=', 'p.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')->whereNotNull('p.sale_id')
            ->where(fn ($q) => $q->whereNull('s.id')->orWhereNull('c.id'))->orderBy('p.id')->pluck('p.id')->all();
        $report['undated_payment_ids'] = DB::table('payments')->whereNotNull('sale_id')->whereNull('created_at')->orderBy('id')->pluck('id')->all();
        $report['payment_method_review_ids'] = DB::table('payments')->whereNotNull('sale_id')
            ->where(fn ($q) => $q->whereNull('paying_method')->orWhereRaw("TRIM(paying_method) = ''")->orWhereIn('paying_method', ['1','2','3','4','5','6','7','8','9']))
            ->orderBy('id')->pluck('id')->all();
        $report['orphan_deposit_ids'] = DB::table('deposits as d')->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
            ->whereNull('c.id')->orderBy('d.id')->pluck('d.id')->all();
        $report['ineligible_sale_payment_ids'] = DB::table('payments as p')->join('sales as s', 's.id', '=', 'p.sale_id')
            ->where(fn ($q) => $q->whereNotNull('s.deleted_at')->orWhere('s.sale_status', '!=', 1))->orderBy('p.id')->pluck('p.id')->all();
        foreach (['sales','received','outstanding','credit','balance'] as $key) $report[$key] = $money->decimal($report[$key]);
        $report['currency_id'] = config('currency_id'); $report['precision'] = $money->precision();
        $report['status'] = $report['review_required'] || $report['orphan_payment_ids'] || $report['undated_payment_ids']
            || $report['orphan_deposit_ids'] || $report['ineligible_sale_payment_ids'] || $report['payment_method_review_ids'] ? 'review_required' : 'reconciled';
        $name = (string) $this->option('output');
        if (str_starts_with($name, '/') || in_array('..', explode('/', $name), true)) { $this->error('Use a path relative to storage/app.'); return self::FAILURE; }
        $path = storage_path('app/'.$name);
        File::ensureDirectoryExists(dirname($path), 0700);
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
        chmod($path, 0600);
        $this->info('Customers: '.$report['customers'].'; review required: '.$report['review_required'].'; status: '.$report['status']);
        $this->line('Report: '.$path);
        return $report['status'] === 'reconciled' ? self::SUCCESS : self::FAILURE;
    }
}
