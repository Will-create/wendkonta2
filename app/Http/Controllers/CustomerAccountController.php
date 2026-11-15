<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Sale;
use App\Services\AccountMoney;
use App\Services\CustomerAccountService;
use App\Services\CustomerStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CustomerAccountController extends Controller
{
    public function index(Request $request, CustomerAccountService $accounts)
    {
        $role = Role::find($request->user()->role_id);
        abort_unless($role?->hasPermissionTo('customers-index'), 403);
        $request->validate(['q' => 'nullable|string|max:100']);
        $query = Customer::where('is_active', true);
        if ($request->filled('q')) $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('phone_number', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%'));
        if ($request->user()->role_id > 2) {
            if (config('staff_access') === 'own') $query->where('user_id', $request->user()->id);
            $query->whereNotExists(function ($q) use ($request) {
                $q->selectRaw('1')->from('sales')->whereColumn('sales.customer_id', 'customers.id')->whereNull('sales.deleted_at')
                    ->where(function ($q) use ($request) {
                        if ($request->user()->warehouse_id) $q->where('warehouse_id', '!=', $request->user()->warehouse_id);
                        if (config('staff_access') === 'own') $q->orWhere('user_id', '!=', $request->user()->id);
                        if (!$request->user()->warehouse_id && config('staff_access') !== 'own') $q->whereRaw('1 = 0');
                    });
            });
        }
        $customers = $query->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();
        return view('backend.customer.accounts', [
            'customers' => $customers, 'summaries' => $accounts->summaries($customers->getCollection()),
            'money' => app(AccountMoney::class),
            'canDeposit' => $role->hasPermissionTo('customers-edit') && $role->hasPermissionTo('sale-payment-add'),
        ]);
    }

    public function authorizeAccount(Request $request, Customer $customer, bool $write = false): void
    {
        $role = Role::find($request->user()->role_id);
        abort_unless($role?->hasPermissionTo('customers-index'), 403);
        if ($write) {
            abort_unless($role->hasPermissionTo('customers-edit') && $role->hasPermissionTo('sale-payment-add'), 403);
        }
        if ($request->user()->role_id > 2) {
            $outside = Sale::where('customer_id', $customer->id)->where(function ($query) use ($request) {
                if ($request->user()->warehouse_id) $query->where('warehouse_id', '!=', $request->user()->warehouse_id);
                if (config('staff_access') === 'own') $query->orWhere('user_id', '!=', $request->user()->id);
                if (!$request->user()->warehouse_id && config('staff_access') !== 'own') $query->whereRaw('1 = 0');
            })->exists();
            abort_if($outside, 403, __('credit.full_account_permission'));
            if (config('staff_access') === 'own') abort_unless((int) $customer->user_id === (int) $request->user()->id, 403);
        }
    }

    public function show(Request $request, Customer $customer, CustomerAccountService $accounts, CustomerStatement $statements)
    {
        $this->authorizeAccount($request, $customer);
        $filters = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d'.($request->filled('start_date') ? '|after_or_equal:start_date' : ''),
            'type' => 'nullable|in:sale,payment,allocation,deposit,return,refund,reversal', 'page' => 'nullable|integer|min:1',
        ]);
        $summary = $accounts->summary($customer);
        $report = $statements->report($customer, $filters, $request->boolean('print'));
        $role = Role::find($request->user()->role_id);
        return view($request->boolean('print') ? 'backend.customer.statement' : 'backend.customer.account', [
            'customer' => $customer, 'summary' => $summary, 'report' => $report, 'filters' => $filters,
            'money' => app(AccountMoney::class), 'accounts' => Account::where('is_active', true)->get(),
            'receiptToken' => old('receipt_token', (string) Str::uuid()),
            'canDeposit' => $role->hasPermissionTo('customers-edit') && $role->hasPermissionTo('sale-payment-add'),
            'canSell' => $role->hasPermissionTo('sales-add'),
        ]);
    }

    public function store(Request $request, Customer $customer, CustomerAccountService $accounts)
    {
        $this->authorizeAccount($request, $customer, true);
        $data = $request->validate([
            'receipt_token' => 'required|uuid', 'amount' => 'required|string|max:18',
            'transaction_date' => 'required|date_format:Y-m-d\TH:i,Y-m-d\TH:i:s|before_or_equal:now',
            'payment_method' => 'required|in:Cash,Bank Transfer,Cheque',
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_active', true)],
            'reference' => 'nullable|string|max:191', 'note' => 'nullable|string|max:2000',
        ]);
        $deposit = $accounts->deposit($customer, $data, $request->user()->id);
        return redirect()->route('customer.account.receipt', [$customer, $deposit]);
    }

    public function receipt(Request $request, Customer $customer, Deposit $deposit, CustomerAccountService $accounts)
    {
        $this->authorizeAccount($request, $customer);
        abort_unless((int) $deposit->customer_id === (int) $customer->id && $deposit->receipt_token, 404);
        return view('backend.customer.receipt', [
            'customer' => $customer, 'deposit' => $deposit, 'summary' => $accounts->summary($customer),
            'money' => app(AccountMoney::class), 'allocations' => DB::table('customer_deposit_allocations as a')
                ->join('sales as s', 'a.sale_id', '=', 's.id')->where('deposit_id', $deposit->id)
                ->select('a.*', 's.reference_no')->get(),
            'canReverse' => Role::find($request->user()->role_id)?->hasPermissionTo('sale-payment-delete'),
        ]);
    }

    public function reverse(Request $request, Customer $customer, Deposit $deposit, CustomerAccountService $accounts)
    {
        $this->authorizeAccount($request, $customer, true);
        abort_unless(Role::find($request->user()->role_id)?->hasPermissionTo('sale-payment-delete'), 403);
        abort_unless((int) $deposit->customer_id === (int) $customer->id, 404);
        $data = $request->validate(['note' => 'required|string|min:3|max:2000']);
        $accounts->reverse($deposit, $data['note'], $request->user()->id);
        return redirect()->route('customer.account.receipt', [$customer, $deposit]);
    }
}
