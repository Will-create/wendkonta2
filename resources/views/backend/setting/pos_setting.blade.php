@extends('backend.layout.main') @section('content')

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{__('db.POS Setting')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'setting.posStore', 'method' => 'post']) !!}
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{__('db.Default Customer')}} *</label>
                                        @if($lims_pos_setting_data)
                                        <input type="hidden" name="customer_id_hidden" value="{{$lims_pos_setting_data->customer_id}}">
                                        @endif
                                        <select required name="customer_id" id="customer_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select customer...">
                                            @foreach($lims_customer_list as $customer)
                                            <option value="{{$customer->id}}">{{$customer->name . ' (' . $customer->phone_number . ')'}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{__('db.Default Biller')}} *</label>
                                        @if($lims_pos_setting_data)
                                        <input type="hidden" name="biller_id_hidden" value="{{$lims_pos_setting_data->biller_id}}">
                                        @endif
                                        <select required name="biller_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select Biller...">
                                            @foreach($lims_biller_list as $biller)
                                            <option value="{{$biller->id}}">{{$biller->name . ' (' . $biller->company_name . ')'}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{__('db.Default Warehouse')}} *</label>
                                        @if($lims_pos_setting_data)
                                        <input type="hidden" name="warehouse_id_hidden" value="{{$lims_pos_setting_data->warehouse_id}}">
                                        @endif
                                        <select required name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select warehouse...">
                                            @foreach($lims_warehouse_list as $warehouse)
                                            <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{__('db.Displayed Number of Product Row')}} *</label>
                                        <input type="number" name="product_number" class="form-control" value="@if($lims_pos_setting_data){{$lims_pos_setting_data->product_number}}@endif" required />
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    @if($lims_pos_setting_data && $lims_pos_setting_data->keybord_active)
                                    <input class="mt-2" type="checkbox" name="keybord_active" value="1" checked>
                                    @else
                                    <input class="mt-2" type="checkbox" name="keybord_active" value="1">
                                    @endif
                                    <label class="mt-2">{{__('db.Touchscreen keybord')}}</label>
                                </div>
                                <div class="col-md-4">
                                    @if($lims_pos_setting_data && $lims_pos_setting_data->is_table)
                                    <input class="mt-2" type="checkbox" name="is_table" value="1" checked>
                                    @else
                                    <input class="mt-2" type="checkbox" name="is_table" value="1">
                                    @endif
                                    <label class="mt-2">{{__('db.Table Management')}}</label>
                                </div>
                                <div class="col-md-4">
                                    @if($lims_pos_setting_data && $lims_pos_setting_data->send_sms)
                                    <input class="mt-2" type="checkbox" name="send_sms" value="1" checked>
                                    @else
                                    <input class="mt-2" type="checkbox" name="send_sms" value="1">
                                    @endif
                                    <label class="mt-2">{{__('db.Send SMS After Sale')}}</label>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <h4>Payment Options</h4>
                                </div>
                                <div class="col-md-12 d-flex justify-content-between">
                                    <div class="form-group d-inline">
                                        @if(in_array("cash",$options))
                                        <input class="mt-2" type="checkbox" name="options[]" value="cash" checked>
                                        @else
                                        <input class="mt-2" type="checkbox" name="options[]" value="cash">
                                        @endif
                                        <label class="mt-2">Cash</label>
                                    </div>

                                    <div class="form-group d-inline">
                                        @if(in_array("card",$options))
                                        <input class="mt-2" type="checkbox" name="options[]" value="card" checked>
                                        @else
                                        <input class="mt-2" type="checkbox" name="options[]" value="card">
                                        @endif
                                        <label class="mt-2">Card</label>
                                    </div>

                                    <div class="form-group d-inline">
                                        @if(in_array("cheque",$options))
                                        <input class="mt-2" type="checkbox" name="options[]" value="cheque" checked>
                                        @else
                                        <input class="mt-2" type="checkbox" name="options[]" value="cheque">
                                        @endif
                                        <label class="mt-2">Cheque</label>
                                    </div>

                                    <div class="form-group d-inline">
                                        @if(in_array("gift_card",$options))
                                        <input class="mt-2" type="checkbox" name="options[]" value="gift_card" checked>
                                        @else
                                        <input class="mt-2" type="checkbox" name="options[]" value="gift_card">
                                        @endif
                                        <label class="mt-2">Gift Card</label>
                                    </div>

                                    <div class="form-group d-inline">
                                        @if(in_array("deposit",$options))
                                        <input class="mt-2" type="checkbox" name="options[]" value="deposit" checked>
                                        @else
                                        <input class="mt-2" type="checkbox" name="options[]" value="deposit">
                                        @endif
                                        <label class="mt-2">Deposit</label>
                                    </div>
                                    <div class="form-group d-inline">
                                        @if(in_array("pesapal",$options))
                                        <input class="mt-2" type="checkbox" name="options[]" value="pesapal" checked>
                                        @else
                                        <input class="mt-2" type="checkbox" name="options[]" value="pesapal">
                                        @endif
                                        <label class="mt-2">Pesapal</label>
                                    </div>
                                    {{-- <div class="form-group d-inline">
                                        @if(in_array("moneipoint",$options))
                                        <input class="mt-2" type="checkbox" name="options[]" value="moneipoint" checked>
                                        @else
                                        <input class="mt-2" type="checkbox" name="options[]" value="moneipoint">
                                        @endif
                                        <label class="mt-2">Moneipoint</label>
                                    </div> --}}
                                </div>
                            </div>
                            <div class="form-inline row mt-3">
                                <div class="form-group col-md-12">
                                     <button type="button" class="btn btn-info add-more">+ {{__('db.Add More Payment Option')}}</button>
                                </div>
                            </div>
                            @if (session('duplicate_message'))
                                <p class="alert alert-danger">{{ session('duplicate_message') }}</p>
                            @endif
                            <div class="row mt-2">
                                <div class="form-inline col-md-4 form-group mt-2" id="payment-options">
                                    @foreach($options as $option)
                                    @if($option !== 'cash' && $option !== 'card' && $option !== 'card' && $option !== 'cheque' && $option !== 'gift_card' && $option !== 'deposit' && $option !== 'paypal' && $option !== 'pesapal')
                                    <div>
                                        <input type="text" value="{{$option}}" class="form-control mt-2" placeholder="{{ __('db.Payment Options') }}" name="options[]">&nbsp;<button class="btn btn-danger remove">X</button>
                                    </div>
                                    @endif
                                    @endforeach
                                </div>
                            </div>
                            <div class="form-group mt-3">
                                <input type="submit" value="{{__('db.submit')}}" class="btn btn-primary">
                            </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">

    $("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #pos-setting-menu").addClass("active");



    $('select[name="customer_id"]').val($("input[name='customer_id_hidden']").val());
    $('select[name="biller_id"]').val($("input[name='biller_id_hidden']").val());
    $('select[name="warehouse_id"]').val($("input[name='warehouse_id_hidden']").val());
    $('.selectpicker').selectpicker('refresh');

    if($('input[name="invoice_size"]:checked').val() == 'thermal'){
        $('#collapseThermal').addClass('show');
    }

    $('input[name="invoice_size"]').on('click',function(){
        if($('input[name="invoice_size"]:checked').val() == 'thermal'){
            $('#collapseThermal').addClass('show');
        } else {
            $('#collapseThermal').removeClass('show');
        }
    });

    $('.add-more').on('click',function(){
        $('#payment-options').append(`<div><input type="text" class="form-control mt-2" placeholder="${@json(__('db.Payment Options'))}" name="options[]">&nbsp;<button class="btn btn-danger remove">X</button></div>`);

    })
    $(document).on("click", '.remove', function() {
        $(this).parent().remove();
    });

</script>
@endpush
