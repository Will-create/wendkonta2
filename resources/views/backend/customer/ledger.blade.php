<table class="table sp-ledger-table"><thead><tr>
    <th>{{ __('db.Date') }}</th><th>{{ __('credit.type') }}</th><th>{{ __('db.Reference No') }}</th><th>{{ __('db.Note') }}</th><th>{{ __('credit.debit') }}</th><th>{{ __('credit.credit_entry') }}</th><th>{{ __('credit.balance') }}</th><th>{{ __('credit.responsible') }}</th>
</tr></thead><tbody>
@forelse($report['rows'] as $row)
<tr>
    <td data-label="{{ __('db.Date') }}">{{ $row->date }}</td><td data-label="{{ __('credit.type') }}">{{ __('credit.'.$row->type) }}@if($row->type === 'allocation')<br><small>{{ $money->decimal($money->units($row->amount)) }}</small>@endif
        @if($row->type !== 'sale' && $row->sale_reference)<br><small>{{ $row->sale_reference }}</small>@endif
        @if($row->payment_method)<br><small>{{ __('db.'.$row->payment_method) }}</small>@endif
    </td>
    <td data-label="{{ __('db.Reference No') }}">@if(in_array($row->type, ['deposit','reversal']) && $row->managed_receipt)<a href="{{ route('customer.account.receipt', [$customer, $row->id]) }}">DEP-{{ $row->id }}</a>@else{{ $row->reference }}@endif</td>
    <td data-label="{{ __('db.Note') }}">{{ $row->note }}</td><td data-label="{{ __('credit.debit') }}">{{ $row->delta < 0 ? $money->decimal(-$money->units($row->delta)) : '—' }}</td><td data-label="{{ __('credit.credit_entry') }}">{{ $row->delta > 0 ? $money->decimal($money->units($row->delta)) : '—' }}</td><td data-label="{{ __('credit.balance') }}">{{ $money->decimal($row->balance) }}</td><td data-label="{{ __('credit.responsible') }}">{{ $row->responsible ?? '—' }}</td>
</tr>
@empty
<tr><td colspan="8">{{ __('credit.empty_history') }}</td></tr>
@endforelse
</tbody></table>
