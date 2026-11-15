@extends('backend.layout.main')
@section('content')
<section><div class="container-fluid">
    <div class="card"><div class="card-header"><h3>{{ __('credit.account') }}</h3></div><div class="card-body">
        <form method="get" class="sp-account-search">
            <label for="account-search">{{ __('mobile.search') }}</label>
            <input id="account-search" name="q" class="form-control" value="{{ request('q') }}" placeholder="{{ __('credit.search_customer') }}" maxlength="100">
            <button class="btn btn-primary" type="submit">{{ __('mobile.search') }}</button>
        </form>
    </div></div>
    <div class="sp-customer-accounts">
        @forelse($customers as $customer)
        @php
            $summary = $summaries[$customer->id];
            $state = $summary['balance'] < 0 ? 'owes' : ($summary['balance'] > 0 ? 'has_credit' : 'settled');
        @endphp
        <article class="sp-customer-account" data-account-state="{{ $state }}">
            <header><h4><a href="{{ route('customer.account', $customer) }}">{{ $customer->name }}</a></h4><span class="sp-account-state">{{ __('credit.'.$state) }}</span></header>
            <p class="sp-account-contact">{{ $customer->phone_number }}@if($customer->email)<br>{{ $customer->email }}@endif</p>
            <div class="sp-account-balance"><span>{{ __('credit.balance') }}</span><strong>{{ $money->decimal($summary['balance']) }} {{ config('currency') }}</strong></div>
            <dl class="sp-account-metrics">
                @foreach(['purchases','received','outstanding','credit'] as $key)
                <div><dt>{{ __('credit.'.$key) }}</dt><dd>{{ $money->decimal($summary[$key]) }} {{ config('currency') }}</dd></div>
                @endforeach
            </dl>
            @if(!$summary['reconciled'])<p class="sp-account-review" role="status">{{ __('credit.review_required') }}</p>@endif
            @if($summary['foreign_sales']->isNotEmpty())<p class="sp-account-review">{{ __('credit.foreign_currency') }} @foreach($summary['foreign_sales'] as $sale){{ $sale->reference_no }} @endforeach</p>@endif
            <footer>
                @if($canDeposit)<a class="btn btn-primary sp-direct-deposit" href="{{ route('customer.account', $customer) }}#customer-deposit">{{ __('credit.make_deposit') }}</a>@endif
                <a class="btn btn-outline-primary" href="{{ route('customer.account', $customer) }}#customer-history">{{ __('credit.history') }}</a>
            </footer>
        </article>
        @empty
        <p role="status">{{ __('credit.no_customers') }}</p>
        @endforelse
    </div>
    <nav class="sp-account-pagination" aria-label="{{ __('mobile.pagination') }}">
        @if($customers->previousPageUrl())<a class="btn btn-outline-primary" href="{{ $customers->previousPageUrl() }}">{{ __('mobile.previous') }}</a>@endif
        <span>{{ $customers->currentPage() }} / {{ $customers->lastPage() }} · {{ $customers->total() }}</span>
        @if($customers->nextPageUrl())<a class="btn btn-outline-primary" href="{{ $customers->nextPageUrl() }}">{{ __('mobile.next') }}</a>@endif
    </nav>
</div></section>
@endsection
