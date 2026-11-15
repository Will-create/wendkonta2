@auth
@php
    $mobilePermissions = isset($role_has_permissions_list) ? $role_has_permissions_list->pluck('name')->all() : [];
    $mobileCan = function ($permission) use ($mobilePermissions) { return in_array($permission, $mobilePermissions); };
@endphp
<header class="sp-mobile-header d-print-none">
    <a class="sp-brand" href="{{ url('/dashboard') }}">{{ $general_setting->site_title ?? 'SalePro POS' }}</a>
    <div class="sp-header-actions">
        <button type="button" class="sp-install" hidden>{{ __('mobile.install') }}</button>
        @if($mobileCan('purchases-add'))
        <a class="sp-sell sp-buy" href="{{ url('/purchases/create') }}"><i class="fa fa-truck" aria-hidden="true"></i> {{ __('mobile.buy') }}</a>
        @endif
        @if($mobileCan('sales-add'))
        <a class="sp-sell" href="{{ url('/sales/create') }}"><i class="fa fa-plus" aria-hidden="true"></i> {{ __('mobile.sell') }}</a>
        @endif
    </div>
</header>
<nav class="sp-bottom-nav d-print-none" aria-label="{{ __('mobile.navigation') }}">
    <a href="{{ url('/dashboard') }}" @if(request()->is('dashboard*')) aria-current="page" @endif><i class="fa fa-home" aria-hidden="true"></i><span>{{ __('mobile.home') }}</span></a>
    @if($mobileCan('sales-index'))
    <a href="{{ url('/sales') }}" @if(request()->is('sales*')) aria-current="page" @endif><i class="fa fa-shopping-cart" aria-hidden="true"></i><span>{{ __('mobile.sales') }}</span></a>
    @endif
    @if($mobileCan('purchases-index'))
    <a href="{{ url('/purchases') }}" @if(request()->is('purchases*')) aria-current="page" @endif><i class="fa fa-truck" aria-hidden="true"></i><span>{{ __('mobile.purchases') }}</span></a>
    @endif
    @if($mobileCan('account-index') || $mobileCan('customers-index'))
    <a href="{{ $mobileCan('account-index') ? url('/accounts') : route('customer.accounts') }}" @if(request()->is('accounts*','customer*','expenses*','money-transfer*','cash-register*')) aria-current="page" @endif><i class="fa fa-money" aria-hidden="true"></i><span>{{ __('mobile.finance') }}</span></a>
    @endif
    <button class="sp-menu-toggle" type="button" aria-expanded="false" aria-controls="salepro-mobile-menu"><i class="fa fa-bars" aria-hidden="true"></i><span>{{ __('mobile.more') }}</span></button>
</nav>
<button class="sp-menu-backdrop d-print-none" type="button" aria-label="{{ __('mobile.close_menu') }}" hidden></button>
<div class="sp-desktop-tools d-print-none">
    <button type="button" class="sp-install" hidden>{{ __('mobile.install') }}</button>
</div>
@endauth
