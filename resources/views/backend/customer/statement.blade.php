<!doctype html><html lang="{{ app()->getLocale() }}" @if(app()->getLocale() === 'ar') dir="rtl" @endif><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('credit.statement') }} · CL-{{ $customer->id }}</title>
<style>
body{font:14px/1.5 system-ui,sans-serif;color:#20202a;max-width:1100px;margin:24px auto;padding:16px}h1{font-size:26px}.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}.summary div{border:1px solid #ddd;padding:12px;border-radius:8px}table{width:100%;border-collapse:collapse;margin:24px 0;font-size:12px}th,td{padding:8px;border-bottom:1px solid #ddd;text-align:start;overflow-wrap:anywhere}thead{display:table-header-group}tr{break-inside:avoid}button,a.button{padding:12px;min-height:44px;background:#733686;color:white;border:0;border-radius:8px;cursor:pointer}a{color:#633278}@media screen and (max-width:600px){
body{padding:8px;margin:8px}table{font-size:14px}thead{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}tbody,tr{display:block}tr{border:1px solid #ddd;border-radius:8px;padding:10px;margin-block:12px}td{display:grid;grid-template-columns:minmax(80px,35%) minmax(0,1fr);gap:8px;padding:8px 0;border:0}td:before{content:attr(data-label);font-weight:600}
}@page{size:A4 landscape;margin:12mm}@media print{.print-actions{display:none}body{max-width:none;margin:0;padding:0}a{color:inherit;text-decoration:none}}
</style></head><body>
<div class="print-actions"><button type="button" onclick="window.print()">{{ __('credit.print_pdf') }}</button></div>
<h1>{{ config('company_name') }} · {{ __('credit.statement') }}</h1>
<p><strong>{{ $customer->name }}</strong> · CL-{{ $customer->id }}<br>{{ $customer->phone_number }} · {{ $customer->email }}<br>{{ $customer->address }} {{ $customer->city }}</p>
<p>{{ $filters['start_date'] ?? __('credit.inception') }} — {{ $filters['end_date'] ?? now()->toDateString() }} · {{ config('currency') }}</p>
<div class="summary">
    @foreach(['opening','closing'] as $key)<div>{{ __('credit.'.$key) }}<br><strong>{{ $money->decimal($report[$key]) }}</strong></div>@endforeach
    @foreach(['sale','deposit','payment','allocation','reversal'] as $key)<div>{{ __('credit.'.$key) }}<br><strong>{{ $money->decimal($report['totals'][$key]) }}</strong></div>@endforeach
    @foreach(['outstanding','credit'] as $key)<div>{{ __('credit.'.$key) }} ({{ __('credit.current') }})<br><strong>{{ $money->decimal($summary[$key]) }}</strong></div>@endforeach
</div>
<p>{{ __('credit.balance_convention') }} {{ __('credit.allocation_explanation') }}</p>
@if(!$summary['reconciled'])<p>{{ __('credit.reconcile_first') }}</p>@endif
@include('backend.customer.ledger')
<p>{{ __('credit.generated') }} {{ now()->format('Y-m-d H:i') }}</p>
</body></html>
