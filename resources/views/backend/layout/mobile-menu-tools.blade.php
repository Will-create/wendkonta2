@auth
<div class="sp-mobile-menu-tools">
    <button type="button" class="sp-menu-close btn btn-outline-secondary" aria-label="{{ __('mobile.close_menu') }}"><i class="fa fa-times" aria-hidden="true"></i> {{ __('mobile.close_menu') }}</button>
    <button type="button" class="sp-install" hidden>{{ __('mobile.install') }}</button>
    @if(isset($role_has_permissions_list) && $role_has_permissions_list->contains('name', 'products-index'))
    <a href="{{ url('/products') }}"><i class="fa fa-cubes" aria-hidden="true"></i> {{ __('mobile.stock') }}</a>
    @endif
    @if(isset($role_has_permissions_list) && $role_has_permissions_list->contains('name', 'customers-index'))
    <a href="{{ route('customer.accounts') }}"><i class="fa fa-users" aria-hidden="true"></i> {{ __('credit.account') }}</a>
    @endif
    @if((int) Auth::user()->role_id === 1)<a href="{{ route('purchases.defaults.edit') }}">{{ __('mobile.purchase_defaults') }}</a>@endif
    <a href="{{ route('user.profile', ['id' => Auth::id()]) }}"><i class="dripicons-user" aria-hidden="true"></i> {{ __('db.profile') }}</a>
    <a href="{{ url('my-transactions/'.date('Y').'/'.date('m')) }}"><i class="dripicons-swap" aria-hidden="true"></i> {{ __('db.My Transaction') }}</a>
    @if(isset($languages) && $languages->isNotEmpty())
    <details>
        <summary>{{ __('db.language') }}</summary>
        @foreach($languages as $language)
        <a href="{{ url('language_switch/'.$language->id) }}">{{ $language->name }}</a>
        @endforeach
    </details>
    @endif
    <button type="button" class="sp-theme-toggle btn btn-outline-secondary">{{ __('db.Switch Theme') }}</button>
    <form action="{{ route('logout') }}" method="post">
        @csrf
        <button type="submit" class="btn btn-outline-secondary"><i class="dripicons-power" aria-hidden="true"></i> {{ __('db.logout') }}</button>
    </form>
</div>
@endauth
