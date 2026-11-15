@extends('backend.layout.main')
@section('content')
<section><div class="container-fluid"><div class="card"><div class="card-header"><h3>{{ __('credit.receipt') }} DEP-{{ $deposit->id }}</h3></div><div class="card-body">
    @include('shared.errors')
    <p><strong>{{ $customer->name }}</strong> · CL-{{ $customer->id }}<br>{{ $customer->phone_number }}</p>
    <p>{{ $deposit->transaction_date }} · {{ __('db.'.$deposit->payment_method) }} · {{ $deposit->reference }}</p>
    <p>{{ __('credit.responsible') }}: {{ \App\Models\User::find($deposit->user_id)?->name }} · {{ __('credit.generated') }} {{ $deposit->created_at }}</p>
    <h3>{{ $money->decimal($money->units($deposit->amount)) }} {{ config('currency') }}</h3><p>{{ $deposit->note }}</p>
    @if($deposit->reversed_at)<div class="alert alert-warning">{{ __('credit.reversed') }} {{ $deposit->reversed_at }} · {{ \App\Models\User::find($deposit->reversed_by)?->name }} · {{ $deposit->reversal_note }}</div>@endif
    <table class="table"><thead><tr><th>{{ __('db.Reference No') }}</th><th>{{ __('credit.allocation') }}</th></tr></thead><tbody>@forelse($allocations as $allocation)<tr><td>{{ $allocation->reference_no }}</td><td>{{ $money->decimal($money->units($allocation->amount)) }}</td></tr>@empty<tr><td colspan="2">{{ __('credit.unallocated') }}</td></tr>@endforelse</tbody></table>
    <p>{{ __('credit.receipt_excess') }}: {{ $money->decimal($money->units($deposit->amount) - $allocations->sum(fn($allocation) => $money->units($allocation->amount))) }} {{ config('currency') }}</p>
    <p>{{ __('credit.credit') }} ({{ __('credit.current') }}): {{ $money->decimal($summary['credit']) }} {{ config('currency') }}</p>
    <div class="sp-account-actions d-print-none"><button type="button" class="btn btn-primary" onclick="window.print()">{{ __('credit.print_pdf') }}</button><a class="btn btn-outline-primary" href="{{ route('customer.account', $customer) }}">{{ __('credit.account') }}</a></div>
    @if($canReverse && !$deposit->reversed_at)
    <form method="post" action="{{ route('customer.account.reverse', [$customer, $deposit]) }}" class="mt-4 d-print-none">@csrf
        <div class="form-group"><label for="reversal-note">{{ __('credit.reversal_reason') }}</label><textarea id="reversal-note" class="form-control" name="note" required minlength="3" maxlength="2000"></textarea></div>
        <button type="submit" class="btn btn-outline-danger">{{ __('credit.reverse_receipt') }}</button>
    </form>
    @endif
</div></div></div></section>
@endsection
