<div class="modal fade" id="quick-product-modal" tabindex="-1" role="dialog" aria-labelledby="quick-product-title" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 id="quick-product-title">{{ __('mobile.create_product') }}</h5><button type="button" class="close" data-dismiss="modal" aria-label="{{ __('db.Close') }}">×</button></div>
        <form method="post" action="{{ route('purchases.products.store') }}" id="quick-product-form">
            @csrf
            <div class="modal-body">
                <p id="quick-product-error" role="alert" hidden></p>
                @foreach(['name' => 'Name', 'code' => 'Code', 'cost' => 'Cost', 'price' => 'Price'] as $field => $label)
                <div class="form-group"><label for="quick-{{ $field }}">{{ __('db.'.$label) }} *</label><div class="input-group"><input id="quick-{{ $field }}" name="{{ $field }}" class="form-control" required @if(in_array($field, ['cost','price'])) type="number" min="0" step="any" inputmode="decimal" @else type="text" maxlength="255" @endif>
                    @if($field === 'code')<div class="input-group-append"><button type="button" class="btn btn-outline-primary" data-generate-code="{{ route('product.gencode') }}">{{ __('mobile.generate_code') }}</button></div>@endif
                </div>@if($field === 'code')<small data-code-status role="status" aria-live="polite"></small>@endif</div>
                @endforeach
                <div class="form-group"><label for="quick-category">{{ __('db.Category') }} *</label><select id="quick-category" name="category_id" class="form-control" required><option value="">{{ __('db.Select') }}</option>@foreach($quick_categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                <div class="form-group"><label for="quick-unit">{{ __('db.Unit') }} *</label><select id="quick-unit" name="unit_id" class="form-control" required><option value="">{{ __('db.Select') }}</option>@foreach($quick_units as $unit)<option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>@endforeach</select></div>
                <div class="form-group"><label for="quick-tax">{{ __('db.Tax') }}</label><select id="quick-tax" name="tax_id" class="form-control"><option value="">{{ __('db.No Tax') }}</option>@foreach($lims_tax_list as $tax)<option value="{{ $tax->id }}">{{ $tax->name }}</option>@endforeach</select></div>
                <div class="form-group"><label for="quick-tax-method">{{ __('db.Tax Method') }}</label><select id="quick-tax-method" name="tax_method" class="form-control"><option value="1">{{ __('db.Exclusive') }}</option><option value="2">{{ __('db.Inclusive') }}</option></select></div>
                @foreach($quick_fields as $field)
                    @if(!$field->is_admin || (int) Auth::user()->role_id === 1)
                    @php($fieldName = str_replace(' ', '_', strtolower($field->name)))
                    <div class="form-group"><label for="quick-custom-{{ $field->id }}">{{ $field->name }} @if($field->is_required)*@endif</label>
                        @if(in_array($field->type, ['select','multi_select','checkbox','radio']))
                        <select id="quick-custom-{{ $field->id }}" name="{{ $fieldName }}{{ in_array($field->type, ['multi_select','checkbox']) ? '[]' : '' }}" class="form-control" @if(in_array($field->type, ['multi_select','checkbox'])) multiple @endif @required($field->is_required)>
                            @foreach(explode(',', $field->option_value ?? '') as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                        </select>
                        @else
                        <input id="quick-custom-{{ $field->id }}" name="{{ $fieldName }}" class="form-control" type="{{ in_array($field->type, ['number','date']) ? $field->type : 'text' }}" value="{{ $field->default_value }}" @required($field->is_required)>
                        @endif
                    </div>
                    @endif
                @endforeach
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('db.Save') }}</button></div>
        </form>
    </div></div>
</div>
@push('scripts')
<script src="{{ asset('js/quick-product.js') }}?v={{ filemtime(public_path('js/quick-product.js')) }}"></script>
<script>
jQuery(function ($) {
    SaleProQuickProduct(document.getElementById('quick-product-form'), @json(trans('mobile')));
    $('#quick-product-form').on('submit', function (event) {
        event.preventDefault();
        const form = this;
        $.ajax({url: form.action, method: 'POST', data: $(form).serialize(), headers: {Accept: 'application/json'}})
            .done(function (product) {
                form.dataset.spDirty = 'false'; form.dataset.spPending = 'false';
                window.purchasePicker.created(product);
                $('#quick-product-modal').modal('hide'); form.reset();
            }).fail(function (xhr) {
                const data = xhr.responseJSON || {};
                $('#quick-product-error').text(data.errors ? Object.values(data.errors).flat().join(' ') : @json(__('mobile.search_error'))).prop('hidden', false);
                form.querySelector('.modal-body').scrollTop = 0;
            });
    });
});
</script>
@endpush
