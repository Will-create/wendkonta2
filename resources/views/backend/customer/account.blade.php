@extends('backend.layout.main')
@section('content')
<section><div class="container-fluid">
    <div class="card"><div class="card-header"><h3>{{ $customer->name }}</h3><p class="mb-0">{{ $customer->phone_number }} · {{ $customer->email }}<br>{{ $customer->address }} {{ $customer->city }} · CL-{{ $customer->id }}</p></div>
    <div class="card-body">
        @include('shared.errors')
        @if(session('message'))<div class="alert alert-info" role="status">{{ session('message') }}</div>@endif
        @if(!$summary['reconciled'])<div class="alert alert-warning" role="alert">{{ __('credit.reconcile_first') }}</div>@endif
        @if($summary['foreign_sales']->isNotEmpty())<div class="alert alert-info">{{ __('credit.foreign_currency') }}
            @foreach($summary['foreign_sales'] as $sale)<span>{{ $sale->reference_no }}</span>@endforeach
        </div>@endif
        <div class="sp-dashboard-overview">
            @foreach(['balance','outstanding','credit','purchases','received','deposits','allocated'] as $key)
            <div class="sp-summary-card"><span>{{ __('credit.'.$key) }}</span><strong>{{ $money->decimal($summary[$key]) }} {{ config('currency') }}</strong></div>
            @endforeach
            <div class="sp-summary-card"><span>{{ __('credit.unpaid_count') }}</span><strong>{{ $summary['unpaid_count'] }}</strong></div>
        </div>
        <p>{{ __('credit.balance_convention') }}</p>
        <div class="sp-account-actions">
            @if($canSell)<a class="btn btn-primary" href="{{ route('sales.create', ['customer_id' => $customer->id]) }}">{{ __('credit.new_sale') }}</a>@endif
            @if($canDeposit)<a class="btn btn-outline-primary" href="#customer-deposit">{{ __('credit.make_deposit') }}</a>@endif
            <a class="btn btn-outline-primary" href="#customer-history">{{ __('credit.history') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('customer.account', $customer) }}?{{ http_build_query(array_merge($filters, ['print' => 1, 'page' => 1])) }}" target="_blank" rel="noopener">{{ __('credit.statement_pdf') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('customer.account', [$customer, 'type' => 'sale']) }}#customer-history">{{ __('credit.view_sales') }}</a>
        </div>
    </div></div>
    @if($canDeposit)
    <div class="card" id="customer-deposit"><div class="card-header"><h4>{{ __('credit.make_deposit') }}</h4></div><div class="card-body">
        <form method="post" action="{{ route('customer.account.deposit', $customer) }}">
            @csrf
            <input type="hidden" name="receipt_token" value="{{ $receiptToken }}">
            <div class="row">
                <div class="col-md-6 form-group"><label for="deposit-amount">{{ __('db.Amount') }} ({{ config('currency') }}) *</label><input id="deposit-amount" name="amount" type="text" inputmode="decimal" class="form-control" value="{{ old('amount') }}" required></div>
                <div class="col-md-6 form-group"><label for="deposit-date">{{ __('db.Date') }} *</label><input id="deposit-date" name="transaction_date" type="datetime-local" step="1" class="form-control" value="{{ old('transaction_date', now()->format('Y-m-d\TH:i:s')) }}" required></div>
                <div class="col-md-6 form-group"><label for="deposit-method">{{ __('db.Payment Method') }} *</label><select id="deposit-method" name="payment_method" class="form-control" required>@foreach(['Cash','Bank Transfer','Cheque'] as $method)<option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('db.'.$method) }}</option>@endforeach</select></div>
                <div class="col-md-6 form-group"><label for="deposit-account">{{ __('db.Account') }} *</label><select id="deposit-account" name="account_id" class="form-control" required><option value="">{{ __('db.Select') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>{{ $account->name }} · {{ $account->account_no }}</option>@endforeach</select></div>
                <div class="col-md-6 form-group"><label for="deposit-reference">{{ __('db.Reference No') }}</label><input id="deposit-reference" name="reference" class="form-control" value="{{ old('reference') }}" maxlength="191"></div>
                <div class="col-md-6 form-group"><label for="deposit-note">{{ __('db.Note') }}</label><textarea id="deposit-note" name="note" class="form-control" maxlength="2000">{{ old('note') }}</textarea></div>
            </div>
            <p>{{ __('credit.fifo_explanation') }}</p>
            <div><button type="submit" class="btn btn-primary" @disabled(!$summary['reconciled'] || $accounts->isEmpty())>{{ __('credit.confirm_deposit') }}</button></div>
        </form>
    </div></div>
    @endif
    <div class="card" id="customer-history"><div class="card-header"><h4>{{ __('credit.history') }}</h4></div><div class="card-body">
        <form method="get" class="sp-summary-toolbar">
            <label for="history-start">{{ __('credit.from') }}</label><input id="history-start" type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="form-control">
            <label for="history-end">{{ __('credit.to') }}</label><input id="history-end" type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="form-control">
            <label for="history-type">{{ __('credit.type') }}</label><select id="history-type" name="type" class="form-control"><option value="">{{ __('credit.all_activity') }}</option>@foreach(['sale','payment','deposit','allocation','return','refund','reversal'] as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ __('credit.'.$type) }}</option>@endforeach</select>
            <button type="submit" class="btn btn-primary">{{ __('db.Filter') }}</button>
        </form>
        <p>{{ __('credit.opening') }}: {{ $money->decimal($report['opening']) }} · {{ __('credit.closing') }}: {{ $money->decimal($report['closing']) }} {{ config('currency') }}</p>
        @include('backend.customer.ledger')
        @if($report['rows']->lastPage() > 1)
        <nav class="sp-account-pagination" aria-label="{{ __('mobile.pagination') }}">
            @if($report['rows']->previousPageUrl())<a class="btn btn-outline-primary" href="{{ $report['rows']->previousPageUrl() }}#customer-history">{{ __('mobile.previous') }}</a>@endif
            <span>{{ $report['rows']->currentPage() }} / {{ $report['rows']->lastPage() }}</span>
            @if($report['rows']->nextPageUrl())<a class="btn btn-outline-primary" href="{{ $report['rows']->nextPageUrl() }}#customer-history">{{ __('mobile.next') }}</a>@endif
        </nav>
        @endif
    </div></div>
</div></section>
@endsection
