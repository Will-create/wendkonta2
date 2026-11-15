<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\AccountMoney;
use App\Services\CustomerAccountService;
use App\Services\CustomerDepositCash;
use App\Services\CustomerStatement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    public function test_historical_deposit_transfer_is_not_counted_as_a_second_receipt_regardless_of_casing(): void
    {
        $this->invoice(1, '50', extra: ['paid_amount' => 20]);
        \App\Models\Deposit::create(['customer_id' => 1, 'user_id' => 1, 'amount' => 20]);
        DB::table('customers')->where('id', 1)->update(['deposit' => 20, 'expense' => 20]);
        Payment::create(['sale_id' => 1, 'user_id' => 1, 'account_id' => 1, 'payment_reference' => 'TRANSFER', 'amount' => 20, 'paying_method' => 'deposit']);
        $summary = app(CustomerAccountService::class)->summary(Customer::first());
        $this->assertSame(2000, $summary['received']); $this->assertSame(0, $summary['direct_payments']);
        $this->assertSame(-3000, $summary['balance']);
        $this->assertSame(-3000, app(CustomerStatement::class)->report(Customer::first(), [], true)['closing']);
    }
    public function test_historical_cash_and_card_payments_populate_received_totals_without_deposits_or_duplicates(): void
    {
        $this->invoice(1, '100', extra: ['paid_amount' => 100, 'payment_status' => 4]);
        $this->invoice(2, '200', extra: ['paid_amount' => 50, 'payment_status' => 2]);
        foreach ([1 => [100, 'Cash'], 2 => [50, 'Credit Card']] as $id => [$amount, $method]) {
            Payment::create(['sale_id' => $id, 'user_id' => 1, 'account_id' => 1,
                'payment_reference' => 'HISTORICAL-'.$id, 'amount' => $amount, 'paying_method' => $method]);
        }
        $summary = app(CustomerAccountService::class)->summary(Customer::first());
        $this->assertSame(30000, $summary['purchases']); $this->assertSame(15000, $summary['received']);
        $this->assertSame(0, $summary['deposits']); $this->assertSame(15000, $summary['outstanding']);
        $this->assertSame(-15000, $summary['balance']); $this->assertSame(0, $summary['credit']);
        $this->assertTrue($summary['reconciled']);
        $history = app(CustomerStatement::class)->report(Customer::first(), [], true);
        $this->assertCount(2, $history['rows']->where('type', 'payment'));
        $this->assertSame(-15000, $history['closing']);
        foreach ([1, 2] as $run) {
            $this->artisan('customer-accounts:reconcile', ['--output' => 'reports/test-historical-reconciliation.json'])->assertExitCode(0);
            $report = json_decode(file_get_contents(storage_path('app/reports/test-historical-reconciliation.json')), true);
            $this->assertSame('150.00', $report['received']);
        }
        unlink(storage_path('app/reports/test-historical-reconciliation.json'));
        $this->assertSame(0, DB::table('deposits')->count()); $this->assertSame(2, DB::table('payments')->count());
        $this->assertEquals(100, DB::table('sales')->where('id', 1)->value('paid_amount'));
        $this->assertEquals(2, DB::table('sales')->where('id', 2)->value('payment_status'));
    }

    public function test_unproven_historical_paid_amount_is_reported_without_creating_cash_or_changing_invoice(): void
    {
        $this->invoice(1, '100', extra: ['paid_amount' => 50, 'payment_status' => 2]);
        $summary = app(CustomerAccountService::class)->summary(Customer::first());
        $this->assertFalse($summary['reconciled']);
        $this->assertSame('invoice_payment_mismatch', $summary['issues'][0]['type']);
        $this->assertSame(0, $summary['received']);
        $this->artisan('customer-accounts:reconcile', ['--output' => 'reports/test-unreliable-history.json'])->assertExitCode(1);
        $report = json_decode(file_get_contents(storage_path('app/reports/test-unreliable-history.json')), true);
        $this->assertSame(1, $report['review_required']);
        unlink(storage_path('app/reports/test-unreliable-history.json'));
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertEquals(50, DB::table('sales')->value('paid_amount'));
    }

    public function test_historical_overpayment_is_visible_credit_and_reconciles_with_ledger(): void
    {
        $this->invoice(1, '100', extra: ['paid_amount' => 120, 'payment_status' => 4]);
        Payment::create(['sale_id' => 1, 'user_id' => 1, 'account_id' => 1, 'payment_reference' => 'EXCESS', 'amount' => 120, 'paying_method' => 'Cash']);
        $summary = app(CustomerAccountService::class)->summary(Customer::first());
        $this->assertSame(2000, $summary['credit']); $this->assertSame(0, $summary['outstanding']);
        $this->assertSame(2000, $summary['balance']);
        $this->assertSame(2000, app(CustomerStatement::class)->report(Customer::first(), [], true)['closing']);
    }
    protected function setUp(): void
    {
        parent::setUp();
        config(['decimal' => 2, 'currency_id' => 1]);
        Schema::create('customers', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->decimal('deposit', 14, 2)->default(0); $t->decimal('expense', 14, 2)->default(0); $t->timestamps(); });
        Schema::create('accounts', function (Blueprint $t) { $t->increments('id'); $t->boolean('is_active')->default(true); });
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('cash_registers', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->boolean('status')->default(true); });
        Schema::create('sales', function (Blueprint $t) {
            $t->increments('id'); $t->integer('customer_id'); $t->integer('user_id')->default(1); $t->integer('warehouse_id')->default(1);
            $t->string('reference_no'); $t->text('sale_note')->nullable(); $t->decimal('grand_total', 14, 2); $t->decimal('paid_amount', 14, 2)->default(0);
            $t->integer('currency_id')->nullable()->default(1); $t->decimal('exchange_rate', 14, 4)->default(1);
            $t->integer('sale_status')->default(1); $t->integer('payment_status')->default(1); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->increments('id'); $t->integer('sale_id')->nullable(); $t->integer('user_id'); $t->integer('account_id'); $t->integer('cash_register_id')->nullable();
            $t->string('payment_reference'); $t->decimal('amount', 14, 2); $t->decimal('change', 14, 2)->default(0);
            $t->string('paying_method'); $t->text('payment_note')->nullable(); $t->timestamps();
        });
        Schema::create('deposits', function (Blueprint $t) { $t->increments('id'); $t->integer('customer_id'); $t->integer('user_id'); $t->decimal('amount', 14, 2); $t->text('note')->nullable(); $t->timestamps(); });
        Schema::create('returns', function (Blueprint $t) { $t->increments('id'); $t->integer('sale_id'); $t->integer('user_id')->default(1); $t->string('reference_no'); $t->text('return_note')->nullable(); $t->decimal('grand_total', 14, 2); $t->timestamps(); });
        (require database_path('migrations/2026_11_15_000200_add_customer_deposit_receipts.php'))->up();
        DB::table('accounts')->insert(['id' => 1]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Cashier']);
        Customer::create(['id' => 1, 'name' => 'Test customer']);
    }

    private function invoice(int $id, string $total, string $date = '2026-01-01 12:00:00', array $extra = []): void
    {
        DB::table('sales')->insert($extra + ['id' => $id, 'customer_id' => 1, 'reference_no' => 'SALE-'.$id,
            'grand_total' => $total, 'created_at' => $date, 'updated_at' => $date]);
    }

    private function data(string $amount = '150.00'): array
    {
        return ['amount' => $amount, 'receipt_token' => (string) Str::uuid(), 'transaction_date' => '2026-10-01T12:00', 'payment_method' => 'Cash', 'account_id' => 1, 'note' => 'Test deposit'];
    }

    public function test_fifo_partial_payment_and_excess_credit_reconcile(): void
    {
        $this->invoice(2, '80', '2026-02-01 12:00:00');
        $this->invoice(1, '100');
        $service = app(CustomerAccountService::class);
        $receipt = $service->deposit(Customer::first(), $this->data(), 1);
        $this->assertEquals([1 => 100, 2 => 50], DB::table('payments')->pluck('amount', 'sale_id')->all());
        $summary = $service->summary(Customer::first());
        $this->assertSame(-3000, $summary['balance']); $this->assertSame(3000, $summary['outstanding']);
        $this->assertTrue($summary['reconciled']);
        $service->deposit(Customer::first(), $this->data('60.00'), 1);
        $summary = $service->summary(Customer::first());
        $this->assertSame(3000, $summary['credit']); $this->assertSame(0, $summary['outstanding']);
        $this->assertSame(3000, app(CustomerStatement::class)->report(Customer::first(), [], true)['closing']);
        $this->assertEquals(210, app(CustomerDepositCash::class)->total(account: 1));
        $this->assertEquals(0, DB::table('payments')->sum(DB::raw('amount - customer_credit_transfer')));
    }

    public function test_receipt_retry_is_idempotent_and_changed_payload_conflicts(): void
    {
        $this->invoice(1, '100'); $data = $this->data(); $service = app(CustomerAccountService::class);
        $first = $service->deposit(Customer::first(), $data, 1);
        $this->assertSame($first->id, $service->deposit(Customer::first(), $data, 1)->id);
        $this->assertSame(1, DB::table('deposits')->count());
        try { $service->deposit(Customer::first(), array_replace($data, ['amount' => '151.00']), 1); $this->fail('Conflicting token accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
    }

    public function test_reversal_retains_original_payments_and_restores_debt_once(): void
    {
        $this->invoice(1, '100'); $service = app(CustomerAccountService::class);
        $receipt = $service->deposit(Customer::first(), $this->data(), 1);
        $service->reverse($receipt, 'Incorrect receipt', 1); $service->reverse($receipt, 'Retry', 1);
        $this->assertSame(2, DB::table('payments')->count());
        $this->assertEquals(0, DB::table('payments')->sum('amount'));
        $this->assertNotNull(DB::table('customer_deposit_allocations')->first()->reversal_payment_id);
        $summary = $service->summary(Customer::first());
        $this->assertTrue($summary['reconciled']); $this->assertSame(-10000, $summary['balance']);
        $this->assertEquals(0, app(CustomerDepositCash::class)->total(account: 1));
        $this->assertSame(-10000, app(CustomerStatement::class)->report(Customer::first(), [], true)['closing']);
    }

    public function test_direct_payments_are_not_counted_twice(): void
    {
        $this->invoice(1, '100', extra: ['paid_amount' => 40]);
        Payment::create(['sale_id' => 1, 'user_id' => 1, 'account_id' => 1, 'payment_reference' => 'CASH', 'amount' => 40, 'paying_method' => 'Cash']);
        $service = app(CustomerAccountService::class);
        $service->deposit(Customer::first(), $this->data('60.00'), 1);
        $summary = $service->summary(Customer::first());
        $this->assertSame(0, $summary['balance']); $this->assertSame(0, $summary['credit']);
        $this->assertSame(0, app(CustomerStatement::class)->report(Customer::first(), [], true)['closing']);
    }

    public function test_future_drafts_deleted_and_foreign_invoices_do_not_consume_credit(): void
    {
        $this->invoice(1, '20', extra: ['currency_id' => 2, 'exchange_rate' => 2]);
        $this->invoice(2, '20', extra: ['sale_status' => 2]);
        $this->invoice(3, '20', extra: ['deleted_at' => now()]);
        $this->invoice(4, '20', '2026-11-01 12:00:00');
        $service = app(CustomerAccountService::class);
        $service->deposit(Customer::first(), $this->data('50.00'), 1);
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(5000, $service->summary(Customer::first())['credit']);
    }

    public function test_invalid_money_and_historical_mismatches_cannot_write_financial_records(): void
    {
        $service = app(CustomerAccountService::class);
        foreach (['-1', '0', 'NaN', '1.001', '1e3'] as $amount) {
            try { $service->deposit(Customer::first(), $this->data($amount), 1); $this->fail('Invalid amount accepted'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('amount', $e->errors()); }
        }
        DB::table('customers')->where('id', 1)->update(['deposit' => 100]);
        try { $service->deposit(Customer::first(), $this->data(), 1); $this->fail('Unreconciled account accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('customer_id', $e->errors()); }
        $this->assertSame(0, DB::table('deposits')->count());
    }

    public function test_existing_cash_refund_does_not_also_settle_invoice(): void
    {
        $this->invoice(1, '100');
        DB::table('returns')->insert(['sale_id' => 1, 'reference_no' => 'REFUND', 'grand_total' => 20, 'created_at' => '2026-02-01 12:00:00']);
        $summary = app(CustomerAccountService::class)->summary(Customer::first());
        $this->assertSame(10000, $summary['outstanding']);
        $this->assertSame(-10000, app(CustomerStatement::class)->report(Customer::first(), [], true)['closing']);
    }

    public function test_later_pos_spending_of_excess_is_traceable_and_does_not_duplicate_cash(): void
    {
        $service = app(CustomerAccountService::class);
        $receipt = $service->deposit(Customer::first(), $this->data(), 1);
        $this->invoice(1, '40', extra: ['paid_amount' => 40]);
        Payment::create(['sale_id' => 1, 'user_id' => 1, 'account_id' => 1, 'payment_reference' => 'POS', 'amount' => 40, 'paying_method' => 'Deposit']);
        DB::table('customers')->where('id', 1)->update(['expense' => 40]);
        $this->assertEquals(40, DB::table('customer_deposit_allocations')->sum('amount'));
        $this->assertEquals(0, DB::table('payments')->sum(DB::raw('amount - customer_credit_transfer')));
        $service->reverse($receipt, 'Reverse all related payments', 1);
        $summary = $service->summary(Customer::first());
        $this->assertTrue($summary['reconciled']); $this->assertSame(-4000, $summary['balance']);
    }

    public function test_statement_filters_keep_full_account_running_balance(): void
    {
        $this->invoice(1, '100');
        app(CustomerAccountService::class)->deposit(Customer::first(), $this->data('60.00'), 1);
        $report = app(CustomerStatement::class)->report(Customer::first(), ['start_date' => '2026-10-01', 'type' => 'deposit'], true);
        $this->assertSame(-10000, $report['opening']); $this->assertSame(-4000, $report['closing']);
        $this->assertCount(1, $report['rows']); $this->assertSame(-4000, $report['rows'][0]->balance);
    }

    public function test_failed_allocation_rolls_back_receipt_payment_invoice_and_customer(): void
    {
        $this->invoice(1, '100');
        DB::statement("CREATE TRIGGER reject_allocation BEFORE INSERT ON customer_deposit_allocations BEGIN SELECT RAISE(ABORT, 'Test allocation failure'); END");
        try { app(CustomerAccountService::class)->deposit(Customer::first(), $this->data(), 1); $this->fail('Failed allocation committed'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('Test allocation failure', $e->getMessage()); }
        $this->assertSame(0, DB::table('deposits')->count()); $this->assertSame(0, DB::table('payments')->count());
        $this->assertEquals(0, DB::table('sales')->value('paid_amount')); $this->assertEquals(0, Customer::first()->deposit);
    }

    public function test_managed_receipts_and_payments_cannot_be_edited_or_deleted(): void
    {
        $this->invoice(1, '100');
        $receipt = app(CustomerAccountService::class)->deposit(Customer::first(), $this->data(), 1);
        foreach ([fn () => $receipt->update(['amount' => 999]), fn () => $receipt->delete(),
            fn () => Payment::first()->update(['amount' => 999]), fn () => Payment::first()->delete()] as $operation) {
            try { $operation(); $this->fail('Immutable financial record was changed'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        }
        $this->assertEquals(150, $receipt->fresh()->amount);
        $this->assertEquals(100, Payment::first()->amount);
    }

    public function test_account_writes_require_both_customer_and_payment_permissions(): void
    {
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('permission_id'); });
        $role = \Spatie\Permission\Models\Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        foreach (['customers-index','customers-edit','sale-payment-add'] as $name) \Spatie\Permission\Models\Permission::create(['name' => $name, 'guard_name' => 'web']);
        $role->givePermissionTo('customers-index', 'customers-edit');
        $request = \Illuminate\Http\Request::create('/customer/1/account/deposits', 'POST');
        $request->setUserResolver(fn () => new \Illuminate\Auth\GenericUser(['id' => 1, 'role_id' => $role->id]));
        try { app(\App\Http\Controllers\CustomerAccountController::class)->authorizeAccount($request, Customer::first(), true); $this->fail('Missing payment permission accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $role->givePermissionTo('sale-payment-add');
        app(\App\Http\Controllers\CustomerAccountController::class)->authorizeAccount($request, Customer::first(), true);
        $this->assertSame(0, DB::table('deposits')->count());
        $clerk = \Spatie\Permission\Models\Role::create(['id' => 3, 'name' => 'Warehouse clerk', 'guard_name' => 'web']);
        DB::table('roles')->where('id', $clerk->id)->update(['id' => 3]);
        $clerk = \Spatie\Permission\Models\Role::find(3);
        $clerk->givePermissionTo('customers-index', 'customers-edit', 'sale-payment-add');
        $this->invoice(1, '100', extra: ['warehouse_id' => 2]);
        $request->setUserResolver(fn () => new \Illuminate\Auth\GenericUser(['id' => 1, 'role_id' => 3, 'warehouse_id' => 1]));
        try { app(\App\Http\Controllers\CustomerAccountController::class)->authorizeAccount($request, Customer::first(), true); $this->fail('Another warehouse was accessible'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_customer_search_counts_all_matches_before_paginating_and_excludes_inactive_matches(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->integer('customer_group_id')->nullable(); $t->boolean('is_active')->default(true);
            foreach (['company_name','email','phone_number','address','city','country','type'] as $field) $t->string($field)->nullable();
            $t->integer('points')->default(0);
        });
        Schema::create('customer_groups', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('discount_plans', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('discount_plan_customers', function (Blueprint $t) { $t->integer('customer_id'); $t->integer('discount_plan_id'); });
        Schema::create('custom_fields', function (Blueprint $t) { $t->increments('id'); $t->string('belongs_to'); $t->boolean('is_table'); $t->string('name'); });
        Schema::create('reward_point_settings', function (Blueprint $t) { $t->increments('id'); $t->boolean('is_active'); $t->timestamps(); });
        DB::table('customer_groups')->insert(['id' => 1, 'name' => 'Customers']);
        DB::table('reward_point_settings')->insert(['is_active' => false]);
        for ($i = 0; $i < 22; ++$i) DB::table('customers')->insert(['name' => 'Match '.$i, 'customer_group_id' => 1]);
        DB::table('customers')->insert(['name' => 'Hidden', 'phone_number' => 'Match', 'is_active' => false]);
        $request = \Illuminate\Http\Request::create('/customers/customer-data', 'POST', [
            'start' => 10, 'length' => 10, 'search' => ['value' => 'Match'], 'order' => [['dir' => 'asc']], 'all_permission' => [],
        ]);
        ob_start();
        try { app(\App\Http\Controllers\CustomerController::class)->customerData($request); $json = ob_get_contents(); }
        finally { ob_end_clean(); }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(22, $data['recordsFiltered']); $this->assertCount(10, $data['data']);
        $this->assertStringNotContainsString('Hidden', json_encode($data['data']));
        $this->assertStringEndsWith('</ul></div>', $data['data'][0]['options']);
    }
}
