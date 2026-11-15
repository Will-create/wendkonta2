@extends('backend.layout.main')
@section('content')
<section><div class="container-fluid"><div class="card">
    <div class="card-header"><h4>{{ __('mobile.purchase_defaults') }}</h4></div>
    <div class="card-body">
        @if(session('message'))<p role="status">{{ session('message') }}</p>@endif
        @include('shared.errors')
        <form method="post" action="{{ route('purchases.defaults.update') }}">
            @csrf
            <div class="form-group"><label for="default-supplier">{{ __('db.Supplier') }}</label>
                <select id="default-supplier" name="supplier_id" class="form-control" required>
                    @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', $defaults->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>@endforeach
                </select>
            </div>
            <div class="form-group"><label for="default-warehouse">{{ __('db.Warehouse') }}</label>
                <select id="default-warehouse" name="warehouse_id" class="form-control">
                    <option value="">{{ __('mobile.choose_warehouse') }}</option>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $defaults->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-primary" type="submit">{{ __('db.Save') }}</button>
        </form>
    </div>
</div></div></section>
@endsection
