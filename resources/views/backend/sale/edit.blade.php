@extends('backend.layout.main') @section('content')
@push('css')
<style>
    @media print {
        .hidden-print {
            display: none !important;
        }
    }
    #product-results-container{background:#f5f6f7;position: absolute;overflow: hidden;max-height: 300px;overflow-y: auto;padding-top: 10px;top:40px;width:100%;z-index:999}
    #product-results-container .product-img{border-radius: 3px; color: #7c5cc4;font-size:13px;padding-top:7px;padding-bottom:7px;text-align:left}
    #product-results-container .product-img:hover{background-color: #7c5cc4;color: #FFF}
</style>
@endpush

<x-error-message key="not_permitted" />
<x-error-message key="error" />

<?php $authUser = Auth::user()->role_id; ?>

<section id="pos-layout" class="forms hidden-print">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{__('db.Update Sale')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => ['sales.update', $lims_sale_data->id], 'method' => 'put', 'files' => true, 'id' => 'payment-form']) !!}
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.date')}}</label>
                                            <input type="text" name="created_at" class="form-control date" value="{{date($general_setting->date_format, strtotime($lims_sale_data->created_at->toDateString()))}}" />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.reference')}}</label>
                                            <p><strong>{{ $lims_sale_data->reference_no }}</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.customer')}} *</label>
                                            <input type="hidden" name="customer_id_hidden" value="{{ $lims_sale_data->customer_id }}" />
                                            <select required name="customer_id" class="selectpicker form-control" data-live-search="true" id="customer_id" title="{{ __('db.Select customer...') }}">
                                                @foreach($lims_customer_list as $customer)
                                                <option value="{{$customer->id}}">{{$customer->name . ' (' . $customer->phone_number . ')'}}</option>
                                                @endforeach
                                            </select>
                                            <x-validation-error fieldName="customer_id" />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Warehouse')}} *</label>
                                            <input type="hidden" name="warehouse_id_hidden" value="{{$lims_sale_data->warehouse_id}}" />
                                            <select required id="warehouse_id" name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select warehouse...') }}">
                                                @foreach($lims_warehouse_list as $warehouse)
                                                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                                @endforeach
                                            </select>
                                            <x-validation-error fieldName="warehouse_id" />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Biller')}} *</label>
                                            <input type="hidden" name="biller_id_hidden" value="{{$lims_sale_data->biller_id}}" />
                                            <select required name="biller_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select Biller...') }}">
                                                @foreach($lims_biller_list as $biller)
                                                <option value="{{$biller->id}}">{{$biller->name . ' (' . $biller->company_name . ')'}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <label>{{__('db.Select Product')}}</label>
                                        <div class="search-box form-group mb-2" style="position:relative">
                                            <div class="input-group pos">
                                                <input style="border: 1px solid #7c5cc4;" type="text" name="product_code_name" id="product-search-input" placeholder="{{ __('db.Scan/Search product by name/code/IMEI') }}" class="form-control" autofocus />
                                                <button type="button" class="btn btn-primary" onclick="barcode()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-upc" viewBox="0 0 16 16"><path d="M3 4.5a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0z"/></svg></button>
                                            </div>
                                            <div id="product-results-container">

                                            </div>
                                            <div id="no-results-message" style="background-color: #f5f6f7;color: #666; margin-top: 5px;padding: 3px 5px; display: none;">No results found</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-5">
                                    <div class="col-md-12">
                                        <h5>{{__('db.Order Table')}} *</h5>
                                        <div class="table-responsive mt-3">
                                            <table id="myTable" class="table table-hover order-list">
                                                <thead>
                                                    <tr>
                                                        <th>{{__('db.name')}}</th>
                                                        <th>{{__('db.Quantity')}}</th>
                                                        <th>{{__('db.Delivered quantity')}}</th>
                                                        <th>{{__('db.Delivery Status')}}</th>
                                                        <th>{{__('db.Net Unit Price')}}</th>
                                                        <th>{{__('db.Discount')}}</th>
                                                        <th>{{__('db.Tax')}}</th>
                                                        <th>{{__('db.Subtotal')}}</th>
                                                        <th><i class="dripicons-trash"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $temp_unit_name = [];
                                                    $temp_unit_operator = [];
                                                    $temp_unit_operation_value = [];
                                                    ?>
                                                    @foreach($lims_product_sale_data as $product_sale)
                                                    <tr>
                                                    <?php
                                                        $product_data = DB::table('products')->find($product_sale->product_id);
                                                        if($product_sale->variant_id){
                                                            $product_variant_data = \App\Models\ProductVariant::select('id', 'item_code')->FindExactProduct($product_data->id, $product_sale->variant_id)->first();
                                                            $product_variant_id = $product_variant_data->id;
                                                            $product_data->code = $product_variant_data->item_code;
                                                        }
                                                        else
                                                            $product_variant_id = null;
                                                        if($product_data->tax_method == 1){
                                                            $product_price = $product_sale->net_unit_price + ($product_sale->discount / $product_sale->qty);
                                                        }
                                                        elseif ($product_data->tax_method == 2) {
                                                            $product_price =($product_sale->total / $product_sale->qty) + ($product_sale->discount / $product_sale->qty);
                                                        }

                                                        $tax = DB::table('taxes')->where('rate',$product_sale->tax_rate)->first();
                                                        $unit_name = array();
                                                        $unit_operator = array();
                                                        $unit_operation_value = array();
                                                        if($product_data->type == 'standard' || $product_data->type == 'combo') {
                                                            $units = DB::table('units')->where('base_unit', $product_data->unit_id)->orWhere('id', $product_data->unit_id)->get();

                                                            foreach($units as $unit) {
                                                                if($product_sale->sale_unit_id == $unit->id) {
                                                                    array_unshift($unit_name, $unit->unit_name);
                                                                    array_unshift($unit_operator, $unit->operator);
                                                                    array_unshift($unit_operation_value, $unit->operation_value);
                                                                }
                                                                else {
                                                                    $unit_name[]  = $unit->unit_name;
                                                                    $unit_operator[] = $unit->operator;
                                                                    $unit_operation_value[] = $unit->operation_value;
                                                                }
                                                            }
                                                            if($unit_operator[0] == '*'){
                                                                $product_price = $product_price / $unit_operation_value[0];
                                                            }
                                                            elseif($unit_operator[0] == '/'){
                                                                $product_price = $product_price * $unit_operation_value[0];
                                                            }
                                                        }
                                                        else {
                                                            $unit_name[] = 'n/a'. ',';
                                                            $unit_operator[] = 'n/a'. ',';
                                                            $unit_operation_value[] = 'n/a'. ',';
                                                        }
                                                        $temp_unit_name = $unit_name = implode(",",$unit_name) . ',';

                                                        $temp_unit_operator = $unit_operator = implode(",",$unit_operator) .',';

                                                        $temp_unit_operation_value = $unit_operation_value =  implode(",",$unit_operation_value) . ',';

                                                        $product_batch_data = \App\Models\ProductBatch::select('batch_no', 'expired_date')->find($product_sale->product_batch_id);
                                                    ?>
                                                        <td><strong class="edit-product btn btn-link pl-0 pr-0" data-toggle="modal" data-target="#editModal">{{$product_data->name}} <i class="dripicons-document-edit"></i></strong>
                                                        <br>
                                                        <span>{{$product_data->code}}</span>

                                                        @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                                                        @php
                                                            if ($product_data->type == 'combo') {
                                                                $product_list = explode(",", $product_data->product_list); // child products
                                                                $qty_list = explode(",", $product_data->qty_list); // required qty for combo
                                                                $comboQtys = [];
                                                                foreach ($product_list as $index => $child_id) {
                                                                    $requiredQty = $qty_list[$index];
                                                                    $childStock = \App\Models\Product_Warehouse::where('product_id', $child_id)
                                                                        ->where('warehouse_id', $lims_sale_data->warehouse_id)
                                                                        ->value('qty') ?? 0;
                                                                    if($requiredQty > 0){
                                                                        $comboQtys[] = intdiv($childStock, $requiredQty);
                                                                    } else {
                                                                        $comboQtys[] = 0;
                                                                    }
                                                                }
                                                                $product_data->qty = min($comboQtys);
                                                            }
                                                        @endphp

                                                         | {{ __('db.In Stock') }} {{$product_data->qty}} <input type="hidden" class="product-type" value="{{$product_data->type}}" />
                                                        @endif

                                                        <br>
                                                        @if($product_batch_data)
                                                            <br>
                                                            <input type="hidden" class="product-batch-id" name="product_batch_id[]" value="{{$product_sale->product_batch_id}}">
                                                            <input type="text" class="form-control batch-no" name="batch_no[]" value="{{$product_batch_data->batch_no}}" required/>
                                                        @endif
                                                        @if(in_array('restaurant',explode(',',$general_setting->modules)))
                                                        @php
                                                            $toppings = json_decode($product_sale->topping_id, true);
                                                            $toppingTotal = collect($toppings)->sum('price');
                                                        @endphp

                                                        @if(!empty($toppings))
                                                            Includes: {{ collect($toppings)->pluck('name')->implode(', ') }}
                                                        @endif
                                                        @endif
                                                        </td>
                                                        <td>
                                                            <div class="input-group"><span class="input-group-btn">
                                                            @if($product_data->is_imei != 1)
                                                            <button type="button" class="btn btn-default minus mr-1" style="padding:5px 8px"><i class="dripicons-minus"></i></button></span>
                                                            @endif

                                                            <input type="text" class="form-control qty numkey input-number" name="qty[]" value="{{$product_sale->qty}}" style="font-size:13px;max-width:50px;padding: 0 0;text-align:center" step="any" min="1" max="{{($product_sale->qty+$product_data->qty)}}" required/><span class="input-group-btn">

                                                            @if($product_data->is_imei != 1)
                                                            <button type="button" class="btn btn-default plus ml-1" style="padding:5px 8px"><i class="dripicons-plus"></i></button>
                                                            @endif
                                                            </span></div>
                                                        </td>
                                                        <td>
                                                            <div class="input-group">
                                                                <span class="input-group-btn"><button type="button" class="btn btn-default dminus mr-1" style="padding:5px 8px"><i class="dripicons-minus"></i></button></span>
                                                                <input type="text" name="dqty[]" class="form-control dqty numkey input-number" value="{{$product_sale->dqty ?? $product_sale->qty}}" style="font-size:13px;max-width:50px;padding: 0 0;text-align:center" step="any" required/>
                                                                <span class="input-group-btn"><button type="button" class="btn btn-default dplus ml-1" style="padding:5px 8px"><i class="dripicons-plus"></i></button></span>
                                                            </div>
                                                        </td>
                                                        <td class="dstatus">{{$product_sale->dqty ?? $product_sale->qty}}/{{$product_sale->qty}}</td>
                                                        <input type="hidden" name="dstatus[]" class="dstatus-value" value="{{$product_sale->dqty ?? $product_sale->qty}}/{{$product_sale->qty}}"/>

                                                        <td class="net_unit_price">
                                                            @if(in_array('restaurant',explode(',',$general_setting->modules)) && !empty($toppings))

                                                            {{ number_format((float)($product_sale->net_unit_price + $toppingTotal), $general_setting->decimal, '.', '')}}

                                                            @else

                                                            {{ number_format((float)$product_sale->net_unit_price, $general_setting->decimal, '.', '')}}

                                                            @endif

                                                        </td>
                                                        <td class="discount">{{ number_format((float)$product_sale->discount, $general_setting->decimal, '.', '')}}</td>
                                                        <td class="tax">{{ number_format((float)$product_sale->tax, $general_setting->decimal, '.', '')}}</td>
                                                        <td class="sub-total">
                                                            @if(in_array('restaurant',explode(',',$general_setting->modules)) && !empty($toppings))

                                                            {{ number_format((float)($product_sale->total + $toppingTotal), $general_setting->decimal, '.', '')}}

                                                            @else

                                                            {{ number_format((float)$product_sale->total, $general_setting->decimal, '.', '')}}

                                                            @endif
                                                        </td>
                                                        <td><button type="button" class="ibtnDel btn btn-sm btn-danger"><i class="dripicons-trash"></i></button></td>
                                                        <input type="hidden" class="product-code" name="product_code[]" value="{{$product_data->code}}"/>
                                                        <input type="hidden" class="product-id" name="product_id[]" value="{{$product_data->id}}"/>
                                                        <input type="hidden" class="product_type" name="product_type[]" value="{{$product_data->type}}"/>
                                                        <input type="hidden" name="product_variant_id[]" value="{{$product_variant_id}}"/>
                                                        <input type="hidden" class="product-price" name="product_price[]" value="{{$product_price}}"/>
                                                        <input type="hidden" class="sale-unit" name="sale_unit[]" value="{{$unit_name}}"/>
                                                        <input type="hidden" class="sale-unit-operator" value="{{$unit_operator}}"/>
                                                        <input type="hidden" class="sale-unit-operation-value" value="{{$unit_operation_value}}"/>
                                                        <input type="hidden" class="net_unit_price" name="net_unit_price[]" value="{{$product_sale->net_unit_price}}" />
                                                        <input type="hidden" class="discount-value" name="discount[]" value="{{$product_sale->discount}}" />
                                                        <input type="hidden" class="tax-rate" name="tax_rate[]" value="{{$product_sale->tax_rate}}"/>
                                                        @if($tax)
                                                        <input type="hidden" class="tax-name" value="{{$tax->name}}" />
                                                        @else
                                                        <input type="hidden" class="tax-name" value="No Tax" />
                                                        @endif
                                                        <input type="hidden" class="tax-method" value="{{$product_data->tax_method}}"/>
                                                        <input type="hidden" class="tax-value" name="tax[]" value="{{$product_sale->tax}}" />
                                                        <input type="hidden" class="subtotal-value" name="subtotal[]" value="{{$product_sale->total}}" />
                                                        <input type="hidden" class="imei-number" name="imei_number[]"  value="{{$product_sale->imei_number}}" />
                                                        <input type="hidden" class="is-imei"  value="{{$product_data->is_imei}}" />

                                                        @if(in_array('restaurant',explode(',',$general_setting->modules)))
                                                        <input type="hidden" class="topping_product" name="topping_product[]"  value="{{$product_sale->topping_id}}" />
                                                        @endif
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="tfoot active">
                                                    <th>{{__('db.Total')}}</th>
                                                    <th id="total-qty" class="text-center">{{$lims_sale_data->total_qty}}</th>
                                                    <th id="total-dqty" class="text-center">0</th>
                                                    <th id="total-dstatus" class="text-center">0/0</th>
                                                    <th></th>
                                                    <th id="total-discount">{{ number_format((float)$lims_sale_data->total_discount, $general_setting->decimal, '.', '')}}</th>
                                                    <th id="total-tax">{{ number_format((float)$lims_sale_data->total_tax, $general_setting->decimal, '.', '')}}</th>
                                                    <th id="total">{{ number_format((float)$lims_sale_data->total_price, $general_setting->decimal, '.', '')}}</th>
                                                    <th><i class="dripicons-trash"></i></th>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_qty" value="{{$lims_sale_data->total_qty}}" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_discount" value="{{$lims_sale_data->total_discount}}" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_tax" value="{{$lims_sale_data->total_tax}}" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_price" value="{{$lims_sale_data->total_price}}" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="item" value="{{$lims_sale_data->item}}" />
                                            <input type="hidden" name="order_tax" value="{{$lims_sale_data->order_tax}}"/>
                                        </div>
                                        <x-validation-error fieldName="item" />
                                    </div>
                                    <div class="col-md-2">
                                        @if($lims_sale_data->coupon_id)
                                            @php
                                                $coupon_data = DB::table('coupons')->find($lims_sale_data->coupon_id);
                                            @endphp
                                            <input type="hidden" name="coupon_active" value="1" />
                                            <input type="hidden" name="coupon_type" value="{{$coupon_data->type}}" />
                                            <input type="hidden" name="coupon_amount" value="{{$coupon_data->amount}}" />
                                            <input type="hidden" name="coupon_minimum_amount" value="{{$coupon_data->minimum_amount}}" />
                                            <input type="hidden" name="coupon_discount" value="{{$lims_sale_data->coupon_discount}}">

                                        @else
                                            <input type="hidden" name="coupon_active" value="0" />
                                        @endif
                                        <div class="form-group">
                                            <input type="hidden" name="grand_total" value="{{$lims_sale_data->grand_total}}" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <input type="hidden" name="order_tax_rate_hidden" value="{{$lims_sale_data->order_tax_rate}}">
                                            <label>{{__('db.Order Tax')}}</label>
                                            <select class="form-control" name="order_tax_rate">
                                                <option value="0">No Tax</option>
                                                @foreach($lims_tax_list as $tax)
                                                <option value="{{$tax->rate}}">{{$tax->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Order Discount Type')}}</label>
                                            <select class="form-control" name="order_discount_type">
                                                @if($lims_sale_data->order_discount_type == 'Percentage')
                                                <option value="Percentage">Percentage</option>
                                                <option value="Flat">Flat</option>
                                                @else
                                                <option value="Flat">Flat</option>
                                                <option value="Percentage">Percentage</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                {{__('db.Order Discount Value')}}
                                            </label>
                                            <input type="number" name="order_discount_value" class="form-control" value="@if($lims_sale_data->order_discount_value){{$lims_sale_data->order_discount_value}}@else{{$lims_sale_data->order_discount}}@endif" step="any" />
                                            <input type="hidden" name="order_discount" value="{{$lims_sale_data->order_discount}}" />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                {{__('db.Shipping Cost')}}
                                            </label>
                                            <input type="number" name="shipping_cost" class="form-control" value="{{$lims_sale_data->shipping_cost}}" step="any" />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Attach Document')}}</label> <i class="dripicons-question" data-toggle="tooltip" title="{{ __('db.Only jpg, jpeg, png, gif, pdf, csv, docx, xlsx and txt file is supported') }}"></i>
                                            <input type="file" name="document" class="form-control" />

                                            <x-validation-error fieldName="document" />
                                            <x-validation-error fieldName="extension" />
                                        </div>
                                    </div>
                                    @foreach($custom_fields as $field)
                                        <?php $field_name = str_replace(' ', '_', strtolower($field->name)); ?>
                                        @if(!$field->is_admin || \Auth::user()->role_id == 1)
                                            <div class="{{'col-md-'.$field->grid_value}}">
                                                <div class="form-group">
                                                    <label>{{$field->name}}</label>
                                                    @if($field->type == 'text')
                                                        <input type="text" name="{{$field_name}}" value="{{$lims_sale_data->$field_name}}" class="form-control" @if($field->is_required){{'required'}}@endif>
                                                    @elseif($field->type == 'number')
                                                        <input type="number" name="{{$field_name}}" value="{{$lims_sale_data->$field_name}}" class="form-control" @if($field->is_required){{'required'}}@endif>
                                                    @elseif($field->type == 'textarea')
                                                        <textarea rows="5" name="{{$field_name}}" value="{{$lims_sale_data->$field_name}}" class="form-control" @if($field->is_required){{'required'}}@endif></textarea>
                                                    @elseif($field->type == 'checkbox')
                                                        <br>
                                                        <?php
                                                        $option_values = explode(",", $field->option_value);
                                                        $field_values =  explode(",", $lims_sale_data->$field_name);
                                                        ?>
                                                        @foreach($option_values as $value)
                                                            <label>
                                                                <input type="checkbox" name="{{$field_name}}[]" value="{{$value}}" @if(in_array($value, $field_values)) checked @endif @if($field->is_required){{'required'}}@endif> {{$value}}
                                                            </label>
                                                            &nbsp;
                                                        @endforeach
                                                    @elseif($field->type == 'radio_button')
                                                        <br>
                                                        <?php
                                                        $option_values = explode(",", $field->option_value);
                                                        ?>
                                                        @foreach($option_values as $value)
                                                            <label class="radio-inline">
                                                                <input type="radio" name="{{$field_name}}" value="{{$value}}" @if($value == $lims_sale_data->$field_name){{'checked'}}@endif @if($field->is_required){{'required'}}@endif> {{$value}}
                                                            </label>
                                                            &nbsp;
                                                        @endforeach
                                                    @elseif($field->type == 'select')
                                                        <?php $option_values = explode(",", $field->option_value); ?>
                                                        <select class="form-control" name="{{$field_name}}" @if($field->is_required){{'required'}}@endif>
                                                            @foreach($option_values as $value)
                                                                <option value="{{$value}}" @if($value == $lims_sale_data->$field_name){{'selected'}}@endif>{{$value}}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($field->type == 'multi_select')
                                                        <?php
                                                        $option_values = explode(",", $field->option_value);
                                                        $field_values =  explode(",", $lims_sale_data->$field_name);
                                                        ?>
                                                        <select class="form-control" name="{{$field_name}}[]" @if($field->is_required){{'required'}}@endif multiple>
                                                            @foreach($option_values as $value)
                                                                <option value="{{$value}}" @if(in_array($value, $field_values)) selected @endif>{{$value}}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($field->type == 'date_picker')
                                                        <input type="text" name="{{$field_name}}" value="{{$lims_sale_data->$field_name}}" class="form-control date" @if($field->is_required){{'required'}}@endif>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Sale Status')}} *</label>
                                            <input type="hidden" name="sale_status_hidden" value="{{$lims_sale_data->sale_status}}" />
                                            <select name="sale_status" class="form-control">
                                                <option value="1">{{__('db.Completed')}}</option>
                                                <option value="2">{{__('db.Pending')}}</option>
                                                @if(in_array('restaurant',explode(',',$general_setting->modules)))
                                                <option value="5">{{__('db.Processing')}}</option>
                                                @endif
                                            </select>
                                            <x-validation-error fieldName="sale_status" />
                                        </div>
                                    </div>
                                    @if($lims_sale_data->coupon_id)
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                <strong>{{__('db.Coupon Discount')}}</strong>
                                            </label>
                                            <p class="mt-2 pl-2"><strong id="coupon-text">{{ number_format((float)$lims_sale_data->coupon_discount, $general_setting->decimal, '.', '')}}</strong></p>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{__('db.Sale Note')}}</label>
                                            <textarea rows="5" class="form-control" name="sale_note" >{{ $lims_sale_data->sale_note }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{__('db.Staff Note')}}</label>
                                            <textarea rows="5" class="form-control" name="staff_note">{{ $lims_sale_data->staff_note }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <input type="hidden" name="payment_status" value="{{$lims_sale_data->payment_status}}" />
                                            <input type="hidden" name="paid_amount" value="{{$lims_sale_data->paid_amount}}" />
                                        </div>
                                        <x-validation-error fieldName="payment_status" />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <input type="hidden" name="draft" value="0" />
                                    <button id="submit-button" type="submit" class="btn btn-primary">{{__('db.submit')}}</button>
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <table class="table table-bordered table-condensed totals">
            <td><strong>{{__('db.Items')}}</strong>
                <span class="pull-right" id="item">{{number_format(0, $general_setting->decimal, '.', '')}}</span>
            </td>
            <td><strong>{{__('db.Total')}}</strong>
                <span class="pull-right" id="subtotal">{{number_format(0, $general_setting->decimal, '.', '')}}</span>
            </td>
            <td><strong>{{__('db.Order Tax')}}</strong>
                <span class="pull-right" id="order_tax">{{number_format(0, $general_setting->decimal, '.', '')}}</span>
            </td>
            <td><strong>{{__('db.Order Discount')}}</strong>
                <span class="pull-right" id="order_discount">{{number_format(0, $general_setting->decimal, '.', '')}}</span>
            </td>
            <td><strong>{{__('db.Shipping Cost')}}</strong>
                <span class="pull-right" id="shipping_cost">{{number_format(0, $general_setting->decimal, '.', '')}}</span>
            </td>
            <td><strong>{{__('db.grand total')}}</strong>
                <span class="pull-right" id="grand_total">{{number_format(0, $general_setting->decimal, '.', '')}}</span>
            </td>
        </table>
    </div>

    <div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="modal_header" class="modal-title"></h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="row modal-element">
                            <div class="col-md-4 form-group">
                                <label>{{__('db.Quantity')}}</label>
                                <input type="number" step="any" name="edit_qty" class="form-control numkey">
                            </div>
                             <div class="col-md-4 form-group">
                                <label>{{__('db.Delivered quantity')}}</label>
                                <input type="number" step="any" name="edit_d_qty" class="form-control numkey">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{__('db.Unit Discount')}}</label>
                                <input type="number" name="edit_discount" class="form-control numkey">
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{__('db.Price Option')}}</strong> </label>
                                    <div class="input-group">
                                      <select class="form-control selectpicker" name="price_option" class="price-option">
                                      </select>
                                  </div>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{__('db.Unit Price')}}</label>
                                <input type="number" name="edit_unit_price" class="form-control numkey" step="any">
                            </div>
                            <?php
                                $tax_name_all[] = 'No Tax';
                                $tax_rate_all[] = 0;
                                foreach($lims_tax_list as $tax) {
                                    $tax_name_all[] = $tax->name;
                                    $tax_rate_all[] = $tax->rate;
                                }
                            ?>
                            <div class="col-md-4 form-group">
                                <label>{{__('db.Tax Rate')}}</label>
                                <select name="edit_tax_rate" class="form-control selectpicker">
                                    @foreach($tax_name_all as $key => $name)
                                    <option value="{{$key}}">{{$name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="edit_unit" class="col-md-4 form-group">
                                <label>{{__('db.Product Unit')}}</label>
                                <select name="edit_unit" class="form-control selectpicker">
                                </select>
                            </div>
                        </div>
                        <button type="button" name="update_btn" class="btn btn-primary">{{__('db.update')}}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="print-layout">
</section>

<div style="width:100%;max-width:350px;position:fixed;top:5%;left:50%;transform:translateX(-50%);z-index:999">
    <button type="button" class="btn btn-danger" id="closeScannerBtn" style="display:none"> X </button>
    <div id="reader" style="width:100%;"></div>
</div>

@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode"></script>
<script>

    $(document).ready(function() {

        calculateTotal();

        $('#product-search-input').focus();

        let typingTimer;
        const doneTypingInterval = 300;
        const $input = $('#product-search-input');
        const $results = $('#product-results-container');
        const $noResults = $('#no-results-message');

        function clearResults() {
            $results.empty().css('padding', '0');
            $noResults.hide();
        }

        function searchProducts(search) {
            $results.css('padding', '0 10px 15px');
            $results.html('<div class="loader " title="4" style="border:none;min-height:300px"><svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="24px" height="30px" viewBox="0 0 24 30" style="enable-background:new 0 0 50 50;" xml:space="preserve"><rect x="0" y="0" width="4" height="10" fill="#333"><animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0" dur="0.6s" repeatCount="indefinite"></animateTransform></rect><rect x="10" y="0" width="4" height="10" fill="#333"><animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0.2s" dur="0.6s" repeatCount="indefinite"></animateTransform></rect><rect x="20" y="0" width="4" height="10" fill="#333"><animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0.4s" dur="0.6s" repeatCount="indefinite"></animateTransform></rect></svg></div>');
            $noResults.hide();

            $.ajax({
                url: '{{url("/")}}/sales/search/' + warehouse_id + '/' + search,
                type: 'GET',
                success: function (data) {
                    $results.empty();
                    if (data.length > 0) {
                        $noResults.hide();
                        data.forEach(function (product) {
                            let productHtml = '';
                            let displayStock = '';

                            if(authUser > 2) {
                                displayStock = '';
                            } else {
                                displayStock = ` | ${product.qty} {{ __('db.In Stock') }} `;
                            }

                            var batch_id = product.product_batch_id ? product.product_batch_id : '';

                            if (product.is_imei == '1' || product.is_imei === 1 || product.is_imei === true) {
                                productHtml = `
                                    <div class="product-img" data-code="${product.code}" 
                                                            data-qty="${product.qty}" 
                                                            data-imei="${product.imei_number}" 
                                                            data-embedded="${product.is_embeded}" 
                                                            data-batch="${batch_id}" 
                                                            data-price="${product.price}">
                                        ${product.name} (${product.code}) | ${product.price} | IMEI: ${product.imei_number}
                                    </div>
                                `;
                            } else if (product.product_batch_id != null) {
                                if(parseInt(product.qty) > 0){
                                    if(product.expired_date == 0) {
                                        product.expired_date = "{{__('db.expired')}}";
                                        var expired = "expired";
                                    } 
                                    productHtml = `
                                        <div class="product-img ${expired}" data-code="${product.code}" 
                                                                            data-qty="${product.qty}" 
                                                                            data-imei="${product.is_imei}" 
                                                                            data-embedded="${product.is_embeded}" 
                                                                            data-batch="${batch_id}" 
                                                                            data-price="${product.price}">
                                            ${product.name} (${product.code}) - ${product.expired_date} | ${product.price} ${displayStock}
                                        </div>
                                    `;
                                }
                            } else {
                                productHtml = `
                                    <div class="product-img" data-code="${product.code}" 
                                                            data-qty="${product.qty}" 
                                                            data-imei="${product.is_imei}" 
                                                            data-embedded="${product.is_embeded}" 
                                                            data-batch="${batch_id}" 
                                                            data-price="${product.price}">
                                        ${product.name} (${product.code}) | ${product.price} ${displayStock}
                                    </div>
                                `;
                            }

                            $results.append(productHtml);
                        });

                        $('.product-img').on('click', function () {
                            clearResults();
                        });

                        // Auto-click if only one result
                        if (data.length === 1) {
                            if(click === 0){
                                $('#product-results-container .product-img').first().trigger('click');
                            }
                            clearResults();
                            click = 1;
                        }

                    } else {
                        clearResults();
                        $noResults.show();
                    }
                },
                error: function () {
                    $noResults.text("Error searching products.").show();
                }
            });
        }

        var click = 0;

        // Trigger on input
        $input.on('input', function () {
            const value = $(this).val().trim();
            if (value.length >= 3) {
                click = 0;
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => searchProducts(value), doneTypingInterval);
            } else {
                clearResults();
            }
        });

        // Trigger on paste
        $input.on('paste', function (e) {
            const pastedData = (e.originalEvent || e).clipboardData.getData('text');
            if (pastedData.length >= 3) {
                click = 0;
                searchProducts(pastedData.trim());
            }
        });

        // Trigger on blur (click outside)
        $input.on('blur', function () {
            setTimeout(() => {
                // only clear if results are still visible/non-empty
                if ($results.children().length > 0) {
                    clearResults();
                }
            }, 150); // slight delay so clicks still register
        });

    });
</script>


<script>
    const closeScannerBtn = document.getElementById("closeScannerBtn");
    const scanner = document.getElementById("reader");
    const html5Qrcode = new Html5Qrcode('reader');

    function barcode() {
        const qrCodeSuccessCallback = (decodedText, decodedResult) => {
            if (decodedText) {
                document.getElementById('lims_productcodeSearch').value = decodedText;
                html5Qrcode.stop();
                closeScannerBtn.style.display = "none";
            }
        };

        const config = {
            fps: 30,
            qrbox: { width: 300, height: 100 },
            // ðŸ‘‡ Add this line to support Code128
            // formatsToSupport: [ Html5QrcodeSupportedFormats.CODE_128 ]
        };

        html5Qrcode.start({ facingMode: "environment" }, config, qrCodeSuccessCallback);
        closeScannerBtn.style.display = "inline-block";
    }

    closeScannerBtn.addEventListener("click", function () {
        closeScannerBtn.style.display = "none";
        html5Qrcode.stop();
    });
</script>
<script type="text/javascript">

    $("ul#sale").siblings('a').attr('aria-expanded','true');
    $("ul#sale").addClass("show");
    $("ul#sale #sale-create-menu").addClass("active");

    @if(config('database.connections.saleprosaas_landlord'))
        numberOfInvoice = <?php echo json_encode($numberOfInvoice)?>;
        $.ajax({
            type: 'GET',
            async: false,
            url: '{{route("package.fetchData", $general_setting->package_id)}}',
            success: function(data) {
                if(data['number_of_invoice'] > 0 && data['number_of_invoice'] <= numberOfInvoice) {
                    localStorage.setItem("message", "You don't have permission to create another invoice as you already exceed the limit! Subscribe to another package if you wants more!");
                    location.href = "{{route('sales.index')}}";
                }
            }
        });
    @endif

    var currency = <?php echo json_encode($currency) ?>;
    var currencyChange = false;
    var without_stock = <?php echo json_encode($general_setting->without_stock) ?>;
    var authUser = <?php echo json_encode($authUser) ?>;

    $('#currency').val(currency['id']);

    $('#currency').change(function(){
        var rate = $(this).find(':selected').data('rate');
        var currency_id = $(this).val();
        $('#exchange_rate').val(rate);
        //$('input[name="currency_id"]').val(currency_id);
        currency['exchange_rate'] = rate;
        $("table.order-list tbody .qty").each(function(index) {
            rowindex = index;
            currencyChange = true;
            cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
            qty = $(this).val();
            $.get('/product-price/' + cur_product_id, function(response) {
                checkDiscount(qty, true, response.price);
            });
        });
    });

    function setCustomerGroupRate(id) {
        $.get('{{ url("sales/getcustomergroup") }}/' + id, function(data) {
            customer_group_rate = (data / 100);
        });
    }

    $('select[name="customer_id"]').val($('input[name="customer_id_hidden"]').val());
    $('select[name="warehouse_id"]').val($('input[name="warehouse_id_hidden"]').val());
    $('select[name="biller_id"]').val($('input[name="biller_id_hidden"]').val());
    $('select[name="sale_status"]').val($('input[name="sale_status_hidden"]').val());
    $('select[name="order_tax_rate"]').val($('input[name="order_tax_rate_hidden"]').val());
    $('.selectpicker').selectpicker('refresh');

$(window).on('load', async function () {

    var customer_id = $('#customer_id').val();
    setCustomerGroupRate(customer_id);
});

// array data depend on warehouse
var lims_product_array = [];
var product_code = [];
var product_name = [];
var product_qty = [];
var product_type = [];
var product_id = [];
var product_list = [];
var variant_list = [];
var qty_list = [];

// array data with selection
var product_price = [];
var wholesale_price = [];
var cost = [];
var product_discount = [];
var tax_rate = [];
var tax_name = [];
var tax_method = [];
var unit_name = [];
var unit_operator = [];
var unit_operation_value = [];
var is_imei = [];
var is_variant = [];
var gift_card_amount = [];
var gift_card_expense = [];
// temporary array
var temp_unit_name = [];
var temp_unit_operator = [];
var temp_unit_operation_value = [];

var exist_type = [];
var exist_code = [];
var exist_qty = [];
var rowindex;
var customer_group_rate;
var row_product_price;
var pos;
var role_id = <?php echo json_encode(Auth::user()->role_id)?>;

var warehouse_id = $('#warehouse_id').val();

var rownumber = $('table.order-list tbody tr:last').index();

for(rowindex  =0; rowindex <= rownumber; rowindex++){
    product_price.push(parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-price').val()));
    exist_code.push($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(2)').text());
    exist_type.push($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-type').val());
    var total_discount = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.discount').text());
    var quantity = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val());
    exist_qty.push(quantity);
    product_discount.push((total_discount / quantity).toFixed({{$general_setting->decimal}}));
    tax_rate.push(parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-rate').val()));
    tax_name.push($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-name').val());
    tax_method.push($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-method').val());
    temp_unit_name = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sale-unit').val().split(',');
    unit_name.push($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sale-unit').val());
    unit_operator.push($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sale-unit-operator').val());
    unit_operation_value.push($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sale-unit-operation-value').val());
    if( !$('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val().includes(null) )
        is_imei.push(1);
    else
        is_imei.push(0);
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sale-unit').val(temp_unit_name[0]);
}

$('.selectpicker').selectpicker({
    style: 'btn-link',
});

$('[data-toggle="tooltip"]').tooltip();

$('select[name="customer_id"]').on('change', function() {
    setCustomerGroupRate($(this).val());
});

//Change quantity
$("#myTable").on('input', '.qty', function() {
    rowindex = $(this).closest('tr').index();

    if($(this).val() < 0 && $(this).val() != '') {
      $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(1);
      alert("Quantity can't be less than 0");
    }
    if(is_variant[rowindex])
        checkQuantity($(this).val(), true);
    else
        checkDiscount($(this).val(), true);

    updateDstatus(rowindex);
});

//Change delivered quantity
$("#myTable").on('input', '.dqty', function() {
    rowindex = $(this).closest('tr').index();
    var dqty = parseFloat($(this).val());

    if (!dqty)
        return;

    var qty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val()) || 0;

    if (dqty < 0) {
        $(this).val(0);
        dqty = 0;
    }

    if (dqty > qty) {
        $(this).val(qty);
        dqty = qty;
    }

    updateDstatus(rowindex);
});

function updateDstatus(rowindex) {
    var qty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val()) || 0;
    var dqty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val()) || 0;
    var dstatus = dqty + ' / ' + qty;
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dstatus').text(dstatus);
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dstatus-value').val(dstatus);
    calculateTotal();
}


//Delete product
$("table.order-list tbody").on("click", ".ibtnDel", function(event) {
    rowindex = $(this).closest('tr').index();
    product_price.splice(rowindex, 1);
    wholesale_price.splice(rowindex, 1);
    product_discount.splice(rowindex, 1);
    tax_rate.splice(rowindex, 1);
    tax_name.splice(rowindex, 1);
    tax_method.splice(rowindex, 1);
    unit_name.splice(rowindex, 1);
    unit_operator.splice(rowindex, 1);
    unit_operation_value.splice(rowindex, 1);
    is_imei.splice(rowindex, 1);
    $(this).closest("tr").remove();
    calculateTotal();
});

//Edit product
$("table.order-list").on("click", ".edit-product", function() {
    rowindex = $(this).closest('tr').index();
    edit();
});

//Update product
$('button[name="update_btn"]').on("click", function() {
    if(is_imei[rowindex]) {
        var imeiNumbers = '';
        $("#editModal .imei-numbers").each(function(i) {
            if (i)
                imeiNumbers += ','+ $(this).val();
            else
                imeiNumbers = $(this).val();
        });
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val(imeiNumbers);
    }

    var edit_discount = $('input[name="edit_discount"]').val();
    var edit_qty = $('input[name="edit_qty"]').val();
    var edit_d_qty = $('input[name="edit_d_qty"]').val();
    var edit_unit_price = $('input[name="edit_unit_price"]').val();

    if (parseFloat(edit_discount) > parseFloat(edit_unit_price)) {
        alert(@json(__('db.Invalid Discount Input!')));
        return;
    }

    if(edit_qty < 0) {
        $('input[name="edit_qty"]').val(1);
        edit_qty = 1;
        alert("Quantity can't be less than 0");
    }

    if(edit_qty < edit_d_qty) {
        $('input[name="edit_d_qty"]').val(edit_qty);
        edit_d_qty = edit_qty;
        alert("Delivered Quantity can't be less more than Quantity: " + edit_qty);
    }

    var tax_rate_all = <?php echo json_encode($tax_rate_all) ?>;
    tax_rate[rowindex]  = parseFloat(tax_rate_all[$('select[name="edit_tax_rate"]').val()]);
    tax_name[rowindex]  = $('select[name="edit_tax_rate"] option:selected').text();

    var product_type = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_type').val();

    product_discount[rowindex] = $('input[name="edit_discount"]').val();
    if(product_type == 'standard'){
        var row_unit_operator = unit_operator[rowindex].slice(0, unit_operator[rowindex].indexOf(","));
        var row_unit_operation_value = unit_operation_value[rowindex].slice(0, unit_operation_value[rowindex].indexOf(","));
        if (row_unit_operator == '*') {
            product_price[rowindex] = $('input[name="edit_unit_price"]').val() / row_unit_operation_value;
        } else {
            product_price[rowindex] = $('input[name="edit_unit_price"]').val() * row_unit_operation_value;
        }
        var position = $('select[name="edit_unit"]').val();
        var temp_operator = temp_unit_operator[position];
        var temp_operation_value = temp_unit_operation_value[position];
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sale-unit').val(temp_unit_name[position]);
        temp_unit_name.splice(position, 1);
        temp_unit_operator.splice(position, 1);
        temp_unit_operation_value.splice(position, 1);

        temp_unit_name.unshift($('select[name="edit_unit"] option:selected').text());
        temp_unit_operator.unshift(temp_operator);
        temp_unit_operation_value.unshift(temp_operation_value);

        unit_name[rowindex] = temp_unit_name.toString() + ',';
        unit_operator[rowindex] = temp_unit_operator.toString() + ',';
        unit_operation_value[rowindex] = temp_unit_operation_value.toString() + ',';
    }
    else {
        product_price[rowindex] = $('input[name="edit_unit_price"]').val();
    }
    product_discount[rowindex] = $('input[name="edit_discount"]').val();
    checkDiscount(edit_qty, false);
    updateDstatus(rowindex);
    //checkQuantity(edit_qty, false);
    $('#editModal').modal('hide');
});

$("#myTable").on('click', '.dplus', function() {
    rowindex = $(this).closest('tr').index();
    var dqty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val()) || 0;
    var qty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val()) || 0;
    if (dqty < qty) {
        dqty++;
    }
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val(dqty);
    updateDstatus(rowindex);
});

$("#myTable").on('click', '.dminus', function() {
    rowindex = $(this).closest('tr').index();
    var dqty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val()) || 0;
    if (dqty > 0) {
        dqty--;
    }
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val(dqty);
    updateDstatus(rowindex);
});

$("#myTable").on('click', '.plus', function() {
    rowindex = $(this).closest('tr').index();
    var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val();
    var max_qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').attr('max');
    if(!qty)
    qty = 1;
    // if(max_qty && parseFloat(qty) >= parseFloat(max_qty)) {
    //     alert(@json(__('db.Maximum quantity reached!')));
    //     return;
    // }
    else
    qty = parseFloat(qty) + 1;
    if(is_variant[rowindex]){
        checkQuantity(String(qty), true);
    }else{
        checkDiscount(qty, true);
    }
});

$("#myTable").on('click', '.minus', function() {
    rowindex = $(this).closest('tr').index();
    var qty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val()) - 1;
    if (qty > 0) {
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(qty);

        if(is_variant[rowindex])
            checkQuantity(String(qty), true);
        else
            checkDiscount(qty, '3');
    }
    else {
        qty = 1;
    }

});

$("select[name=price_option]").on("change", function () {
    $("#editModal input[name=edit_unit_price]").val($(this).val());
});

$("#myTable").on("change", ".batch-no", function () {
    rowindex = $(this).closest('tr').index();
    var product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-id').val();
    var warehouse_id = $('#warehouse_id').val();
    $.get('{{url("/check-batch-availability")}}/' + product_id + '/' + $(this).val() + '/' + warehouse_id, function(data) {
        if(data['message'] != 'ok') {
            alert(data['message']);
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.batch-no').val('');
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-batch-id').val('');
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.expired-date').text('');
        }
        else {
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-batch-id').val(data['product_batch_id']);
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.expired-date').text(data['expired_date']);
            code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-code').val();
            pos = product_code.indexOf(code);
            product_qty[pos] = data['qty'];
        }
    });
});

$(document).on('click', '.product-img', function() {

    var customer_id = $('#customer_id').val();
    var warehouse_id = $('#warehouse_id').val();
    var biller_id = $('#biller_id').val();

    @if(in_array('restaurant',explode(',',$general_setting->modules)))
    var table_id = $('#table_id').val();
    var waiter_id = $('#waiter_id').val();
    var service_id = $('#service_id').val();
    @endif

    var data = $(this).data();
    productSearch(data);
});

function productSearch(data) {
    if(data.embedded == 1) {
        alert('{{ __("db.This product has been added using the weight scale machine.")}}');
        return;
    }
    var item_code = data.code;
    var pre_qty = 0;
    var flag = true;
    $(".product-code").each(function(i) {
        if ($(this).val().trim() == item_code) {
            rowindex = i;
            if(data.imei != 'null' && data.imei != '') {
                imeiNumbers = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .imei-number').val();
                imeiNumbersArray = imeiNumbers.split(",");

                if(imeiNumbersArray.includes(data.imei)) {
                    alert(@json(__('db.Same imei or serial number is not allowed!')));
                    flag = false;
                    $('#product-search-input').val('');
                    return;
                }
            }
            pre_qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val();
        }
    });
    if(flag)
    {
        let product = {
            code: data.code,
            qty: data.qty,
            pre_qty: (parseFloat(pre_qty) + 1),
            imei: data.imei,
            embedded: data.embedded,
            batch: data.batch,
            price: data.price,
            customer_id: $('#customer_id').val()
        };
        $.ajax({
            type: 'GET',
            async: false,
            url: '{{url("sales/lims_product_search")}}',
            data: {
                data: product
            },
            success: function(data) {
                if(data[23]) {
                    data[15] = 1;
                    pre_qty = 0;
                }
                if(pre_qty > 0 && data[21]) {
                    var old_batch = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.batch-no').val();

                    if(old_batch && old_batch != data[22]) {
                        pre_qty = 0;
                        data[15] = 1;
                    }

                }
                var flag = 1;
                if (pre_qty > 0) {
                    var qty = data[15];
                    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(qty);

                    product_price[rowindex] = parseFloat(data[2] * currency['exchange_rate']) + parseFloat(data[2] * currency['exchange_rate'] * customer_group_rate);

                    checkDiscount(String(qty), true);
                    flag = 0;
                }
                $("input[name='product_code_name']").val('');

                if(flag){
                    addNewProduct(data);
                }
                else if(data[18] != 'null' && data[18] != '') {
                    var imeiNumbers = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val();
                    if(imeiNumbers)
                        imeiNumbers += ','+data[18];
                    else
                        imeiNumbers = data[18];
                    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val(imeiNumbers);
                }
            }
        });
    }

}

function addNewProduct(data){
    $('.payment-btn').removeAttr('disabled');
    var newRow = $('<tr id='+ data[1] +'>');
    var cols = '';
    temp_unit_name = (data[6]).split(',');
    pos = product_code.indexOf(data[1]);

    let stockDisplay = '';

    if (authUser > 2) {
        cols += '<td class="product-title"><strong>' + data[0] + '<br><span>' + data[1] + '</span>' + stockDisplay + ' <strong class="product-price d-md-none"></strong>';
    } else {
        if(data[20].trim() == 'standard' || data[20].trim() == 'combo'){
            if (!data[18] || data[18] == 'null') {
                stockDisplay = ` | {{ __('db.In Stock') }} : <span class="in-stock">` + data[19] + `</span>`;
            }
        }
        cols += '<td class="product-title"><strong class="edit-product btn btn-link pl-0 pr-0" data-toggle="modal" data-target="#editModal">' + data[0] + ' <i class="dripicons-document-edit"></i></strong><br><span>' + data[1] + '</span>' + stockDisplay + ' <strong class="product-price d-md-none"></strong>';
    }

    if(data[12]) {
        cols += '<br><input style="font-size:13px;padding:3px 25px 3px 10px;height:30px !important" type="text" class="form-control batch-no" value="'+data[22]+'" required/> <input type="hidden" class="product-batch-id" name="product_batch_id[]" value="'+data[21]+'"/>';
    }
    else {
        cols += '<input type="text" class="form-control batch-no d-none" disabled/> <input type="hidden" class="product-batch-id" name="product_batch_id[]"/>';
    }

    cols += '</td>';
    cols += '<td><div class="input-group"><span class="input-group-btn">';

    // If no IMEI, show minus button
    if (!data[18] || data[18] == 'null') {
        cols += '<button type="button" class="btn btn-default minus mr-1" style="padding:5px 8px"><i class="dripicons-minus"></i></button></span>';
    }

    // Input field
    cols += '<input type="text" name="qty[]" class="form-control qty numkey input-number" style="font-size:13px;max-width:50px;padding: 0 0;text-align:center" step="any" value="'+data[15]+'" max="'+data[19].trim()+'" required><span class="input-group-btn">';

    // If no IMEI, show plus button
    if (!data[18] || data[18] == 'null') {
        cols += '<button type="button" class="btn btn-default plus ml-1" style="padding:5px 8px"><i class="dripicons-plus"></i></button>';
    }

    cols += '</span></div></td>';

    cols += '<td class="product-price"></td>';
    cols += '<td class="discount">0.00</td>';
    cols += '<td class="tax">0.00</td>';

    cols += '<td class="sub-total"></td>';
    // Always show delete button
    cols += '<td><button type="button" class="ibtnDel btn btn-danger btn-sm mr-2"><i class="dripicons-trash"></i></button></td>';

    cols += '<input type="hidden" class="product-code" name="product_code[]" value="' + data[1] + '"/>';
    cols += '<input type="hidden" class="product-id" name="product_id[]" value="' + data[9] + '"/>';
    cols += '<input type="hidden" class="product_type" name="product_type[]" value="' + data[20] + '"/>';
    cols += '<input type="hidden" class="product_price" />';
    cols += '<input type="hidden" class="sale-unit" name="sale_unit[]" value="' + temp_unit_name[0] + '"/>';
    cols += '<input type="hidden" class="net_unit_price" name="net_unit_price[]" />';
    cols += '<input type="hidden" class="discount-value" name="discount[]" />';
    cols += '<input type="hidden" class="tax-rate" name="tax_rate[]" value="' + data[3] + '"/>';
    cols += '<input type="hidden" class="tax-value" name="tax[]" />';
    cols += '<input type="hidden" class="tax-name" value="'+data[4]+'" />';
    cols += '<input type="hidden" class="tax-method" value="'+data[5]+'" />';
    cols += '<input type="hidden" class="sale-unit-operator" value="'+data[7]+'" />';
    cols += '<input type="hidden" class="sale-unit-operation-value" value="'+data[8]+'" />';
    cols += '<input type="hidden" class="subtotal-value" name="subtotal[]" />';
    if(data[18] != 'null' && data[18] != '')
        cols += '<input type="hidden" class="imei-number" name="imei_number[]" value="'+data[18]+'" />';
    else
        cols += '<input type="hidden" class="imei-number" name="imei_number[]" value="" />';
    if(data[23]){
        cols += '<input type="hidden" class="topping_product" name="topping_product[]" value="" />';
        cols += '<input type="hidden" class="topping-price" name="topping-price" value="" />';
    }

    newRow.append(cols);

    $("table.order-list tbody").prepend(newRow);

    rowindex = newRow.index();

    product_price.splice(rowindex, 0, parseFloat(data[2] * currency['exchange_rate']) + parseFloat(data[2] * currency['exchange_rate'] * customer_group_rate));

    if(data[16])
        wholesale_price.splice(rowindex, 0, parseFloat(data[16] * currency['exchange_rate']) + parseFloat(data[16] * currency['exchange_rate'] * customer_group_rate));
    else
        wholesale_price.splice(rowindex, 0, '{{number_format(0, $general_setting->decimal, '.', '')}}');
    cost.splice(rowindex, 0, parseFloat(data[17] * currency['exchange_rate']));
    product_discount.splice(rowindex, 0, '{{number_format(0, $general_setting->decimal, '.', '')}}');
    tax_rate.splice(rowindex, 0, parseFloat(data[3]));
    tax_name.splice(rowindex, 0, data[4]);
    tax_method.splice(rowindex, 0, data[5]);
    unit_name.splice(rowindex, 0, data[6]);
    unit_operator.splice(rowindex, 0, data[7]);
    unit_operation_value.splice(rowindex, 0, data[8]);
    is_imei.splice(rowindex, 0, data[13]);
    is_variant.splice(rowindex, 0, data[14]);

    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_price').val(product_price[rowindex]);

    checkQuantity(data[15], true);
    checkDiscount(data[15], true);

    if(data[16]) {
        populatePriceOption();
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.edit-product').click();
    }

    if (data[23] && Array.isArray(data[23]) && data[23].length > 0) {
        if(productSale && productSale.length > 0) {

            if (product_discount[rowindex] < 1) {
                cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
                @if (isset($draft_product_discount))
                    if (product_discount[rowindex] < 1) {
                        draft_discounts = @json($draft_product_discount['discount']);
                        product_discount[rowindex] = draft_discounts[cur_product_id];
                    }
                @endif
            }

            // Find a match for current data[9] (product_id)
            let matchedIndex = productSale.findIndex(p => parseInt(p.product_id) === parseInt(data[9]));

            if (matchedIndex !== -1) {
                let matchedProduct = productSale[matchedIndex];

                // Parse toppings
                let toppings = JSON.parse(matchedProduct.topping_id || '[]');

                let toppingNames = toppings.map(t => t.name).join(", ");
                let totalToppingPrice = toppings.reduce((sum, t) => sum + parseFloat(t.price), 0);

                newRow.find('.product-title').append(`<br><small>Includes: ${toppingNames}</small>`);
                newRow.find('.topping_product').val(matchedProduct.topping_id);
                newRow.find('.topping-price').val(totalToppingPrice.toFixed({{$general_setting->decimal}}));

                const currentPrice = parseFloat(newRow.find('.product-price').text()) || 0;
                const newPrice = currentPrice + totalToppingPrice;
                newPrice -= product_discount[rowindex];
                newRow.find('.product-price').text(newPrice.toFixed({{$general_setting->decimal}}));
                newRow.find('.sub-total').text(newPrice.toFixed({{$general_setting->decimal}}));

                // Remove used item from array
                productSale.splice(matchedIndex, 1);

                calculateTotal();
            }

        }else{
            openToppingsModal(data, [], rowindex);

            function openToppingsModal(data, selectedToppings = [], rowIndex = null) {
                let modalContent = '<form id="product-selection-form">';
                data[23].forEach(product => {
                    const selected = selectedToppings.find(t => t.id == product.id);
                    const isChecked = selected ? 'checked' : '';
                    const qty = selected ? selected.qty : 1;

                    modalContent += `
                        <div class="form-check d-flex align-items-center mb-1">
                            <div>
                                <input class="form-check-input" type="checkbox" name="productOption" id="product_${product.id}" value="${product.id}" data-name="${product.name}" data-price="${product.price}" ${isChecked}>
                                <label class="form-check-label" for="product_${product.id}">
                                    ${product.name} (${product.code}) - ${product.price}
                                </label>
                            </div>
                            <input type="number" name="quantity_${product.id}" id="quantity_${product.id}" class="form-control form-control-sm" style="width: 80px;" min="1" value="${qty}">
                        </div>`;
                });
                modalContent += '</form>';

                const modalHTML = `
                    <div class="modal fade" id="productSelectionModal" tabindex="-1" role="dialog" aria-labelledby="productSelectionModalLabel" aria-hidden="true" data-rowindex="${rowIndex}">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="productSelectionModalLabel">{{__('db.Select Additional Products')}}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">${modalContent}</div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('db.Cancel')}}</button>
                                    <button type="button" class="btn btn-primary" id="confirmSelection">Confirm</button>
                                </div>
                            </div>
                        </div>
                    </div>`;

                // Remove existing modal if any, then append and show new
                $("#productSelectionModal").remove();
                $("body").append(modalHTML);
                $("#productSelectionModal").modal('show');
            }


            // Handle selection confirmation
            $("#confirmSelection").on('click', function () {
                let selectedToppings = [];
                let totalAdditionalPrice = 0;

                if (product_discount[rowindex] < 1) {
                    cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
                    @if (isset($draft_product_discount))
                        if (product_discount[rowindex] < 1) {
                            draft_discounts = @json($draft_product_discount['discount']);
                            product_discount[rowindex] = draft_discounts[cur_product_id];
                        }
                    @endif
                }

                $("input[name='productOption']:checked").each(function () {
                    const qty = parseFloat($(`#quantity_${$(this).val()}`).val() || 1); // define qty first

                    const topping = {
                        id: $(this).val(),
                        name: $(this).data('name'),
                        qty: qty,
                        price: parseFloat($(this).data('price')) * qty
                    };

                    selectedToppings.push(topping);
                    totalAdditionalPrice += topping.price;
                });

                if (selectedToppings.length > 0) {
                    // Convert the selected toppings array to JSON
                    const selectedToppingsJson = JSON.stringify(selectedToppings);

                    // Append toppings to the main product row
                    const selectedProductNames = selectedToppings.map(t => `${t.name} (${t.qty})`).join(', ');

                    newRow.find('.product-title').append(`<br><small>Includes: ${selectedProductNames}</small>`);

                    newRow.find('.topping_product').val(selectedToppingsJson); // Store JSON in hidden field

                    // Update the total price
                    const currentPrice = parseFloat(newRow.find('.product-price').text()) || 0;
                    let newPrice = currentPrice + totalAdditionalPrice;
                    newPrice -= product_discount[rowindex];
                    newRow.find('.product-price').text(newPrice.toFixed({{$general_setting->decimal}}));
                    newRow.find('.sub-total').text(newPrice.toFixed({{$general_setting->decimal}}));
                    newRow.find('.topping-price').val(totalAdditionalPrice.toFixed({{$general_setting->decimal}}));
                }

                $("#productSelectionModal").modal('hide');
                $(".modal-backdrop").remove();
                $("#productSelectionModal").remove();
                calculateTotal();
            });

            // Stop further processing until the modal is resolved
            return;
        }
    }
}

function populatePriceOption() {
    $('#editModal select[name=price_option]').empty();
    $('#editModal select[name=price_option]').append('<option value="'+ product_price[rowindex] +'">'+ product_price[rowindex] +'</option>');
    if(wholesale_price[rowindex] > 0)
        $('#editModal select[name=price_option]').append('<option value="'+ wholesale_price[rowindex] +'">'+ wholesale_price[rowindex] +'</option>');
    $('.selectpicker').selectpicker('refresh');
}

function edit(){
    $(".imei-section").remove();
    if(is_imei[rowindex]) {

        var imeiNumbers = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val();

        if(imeiNumbers.length) {
            imeiArrays = [...new Set(imeiNumbers.split(","))];
            htmlText = `<div class="col-md-8 form-group imei-section">
                        <label>IMEI or Serial Numbers</label>
                        <div class="table-responsive">
                            <table id="imei-table" class="table table-hover">
                                <tbody>`;
            for (var i = 0; i < imeiArrays.length; i++) {
                htmlText += `<tr>
                                <td>
                                    <input type="text" class="form-control imei-numbers" name="imei_numbers[]" value="`+imeiArrays[i]+`" />
                                </td>
                                <td>
                                    <button type="button" class="imei-del btn btn-sm btn-danger">X</button>
                                </td>
                            </tr>`;
            }
            htmlText += `</tbody>
                            </table>
                        </div>
                    </div>`;
            $("#editModal .modal-element").append(htmlText);
        }
    }
    populatePriceOption();
    $("#product-cost").text(cost[rowindex]);
    var row_product_name_code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(1) > strong:nth-child(1)').text();
    $('#modal_header').text(row_product_name_code);

    var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val();
    $('input[name="edit_qty"]').val(qty);

    var dqty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.dqty').val();
    $('input[name="edit_d_qty"]').val(dqty ? dqty : qty);

    cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
    @if (isset($draft_product_discount))
        if (product_discount[rowindex] < 1) {
            draft_discounts = @json($draft_product_discount['discount']);
            product_discount[rowindex] = draft_discounts[cur_product_id];
        }
    @endif

    $('input[name="edit_discount"]').val(parseFloat(product_discount[rowindex]).toFixed({{$general_setting->decimal}}));

    var tax_name_all = <?php echo json_encode($tax_name_all) ?>;
    pos = tax_name_all.indexOf(tax_name[rowindex]);
    $('select[name="edit_tax_rate"]').val(pos);

    var row_product_code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-code').val();
    var product_type = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_type').val();
    if(product_type == 'standard'){
        unitConversion();
        temp_unit_name = (unit_name[rowindex]).split(',');
        temp_unit_name.pop();
        temp_unit_operator = (unit_operator[rowindex]).split(',');
        temp_unit_operator.pop();
        temp_unit_operation_value = (unit_operation_value[rowindex]).split(',');
        temp_unit_operation_value.pop();
        $('select[name="edit_unit"]').empty();
        $.each(temp_unit_name, function(key, value) {
            $('select[name="edit_unit"]').append('<option value="' + key + '">' + value + '</option>');
        });
        $("#edit_unit").show();
    }
    else{
        row_product_price = product_price[rowindex];
        $("#edit_unit").hide();
    }
    $('input[name="edit_unit_price"]').val(row_product_price.toFixed({{$general_setting->decimal}}));
    $('.selectpicker').selectpicker('refresh');
}

//Delete imei
$(document).on("click", "table#imei-table tbody .imei-del", function() {
    $(this).closest("tr").remove();
    //decreaing qty
    var edit_qty = parseFloat($('input[name="edit_qty"]').val());
    $('input[name="edit_qty"]').val(edit_qty-1);
});

function checkDiscount(qty, flag, price = 0) {
    var customer_id = $('#customer_id').val();
    var warehouse_id = $('#warehouse_id').val();
    var product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();

        $.ajax({
            type: 'GET',
            async: false,
            url: '{{url("/")}}/sales/check-discount?qty='+qty+'&customer_id='+customer_id+'&product_id='+product_id+'&warehouse_id='+warehouse_id,
            success: function(data) {
                if(product_price[rowindex].length == 0){
                    product_price[rowindex] = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product_price').val();
                }
                product_price[rowindex] = parseFloat(product_price[rowindex] * currency['exchange_rate']) + parseFloat(product_price[rowindex] * currency['exchange_rate'] * customer_group_rate);

                var productDiscount = parseFloat($('#discount').text());

                if(flag == true)
                    $('#discount').text(productDiscount+data[2]);
                else if(flag == false)
                    $('#discount').text(productDiscount-data[2]*qty);
                else if(flag == 'input')
                    $('#discount').text(productDiscount-data[2]*previousqty+data[2]*qty);
                else
                    $('#discount').text(productDiscount-data[2]);
            }
        });

    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(qty);
    flag = true;
    checkQuantity(String(qty), flag);
}

function checkQuantity(sale_qty, flag) {
    var row_product_code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-code').val();
    var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').attr('max');
    var product_type = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_type').val();
    if(without_stock == 'no') {
        if(product_type.trim() == 'standard' || product_type.trim() == 'combo') {
            var operator = unit_operator[rowindex].split(',');
            var operation_value = unit_operation_value[rowindex].split(',');
            if(operator[0] == '*')
                total_qty = sale_qty * operation_value[0];
            else if(operator[0] == '/')
                total_qty = sale_qty / operation_value[0];
            if (total_qty > parseFloat(qty)) {
                alert(@json(__('db.Quantity exceeds stock quantity!')));
                if (flag) {
                    sale_qty = (sale_qty - 1);
                    checkQuantity(sale_qty, true);
                }
                else {
                    edit();
                    return;
                }
            }
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
        }
    }
    else
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
    if(!flag) {
        $('#editModal').modal('hide');
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
    }
    calculateRowProductData(sale_qty);
}

function unitConversion() {
    var row_unit_operator = unit_operator[rowindex].slice(0, unit_operator[rowindex].indexOf(","));
    var row_unit_operation_value = unit_operation_value[rowindex].slice(0, unit_operation_value[rowindex].indexOf(","));

    if (row_unit_operator == '*') {
        row_product_price = product_price[rowindex] * row_unit_operation_value;
    } else {
        row_product_price = product_price[rowindex] / row_unit_operation_value;
    }
}

function calculateRowProductData(quantity) {
    if (product_discount[rowindex] < 1) {
        cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
        @if (isset($draft_product_discount))
            if (product_discount[rowindex] < 1) {
                draft_discounts = @json($draft_product_discount['discount']);
                product_discount[rowindex] = draft_discounts[cur_product_id];
            }
        @endif
    }

    if(product_type[pos] == 'standard')
        unitConversion();
    else
        row_product_price = product_price[rowindex];
    if (tax_method[rowindex] == 1) {
        var net_unit_price = row_product_price - product_discount[rowindex];
        var tax = net_unit_price * quantity * (tax_rate[rowindex] / 100);
        var sub_total = (net_unit_price * quantity) + tax;

        if(parseFloat(quantity))
            var sub_total_unit = sub_total / quantity;
        else
            var sub_total_unit = sub_total;
    }
    else {
        console.log(`${row_product_price} - ${product_discount[rowindex]} - ${tax_rate[rowindex]} -`);
        var sub_total_unit = row_product_price - product_discount[rowindex];
        var net_unit_price = (100 / (100 + tax_rate[rowindex])) * sub_total_unit;
        var tax = (sub_total_unit - net_unit_price) * quantity;
        var sub_total = sub_total_unit * quantity;
    }

    var topping_price = ($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.topping-price').val() * quantity) || 0;

    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.discount-value').val((product_discount[rowindex] * quantity).toFixed({{$general_setting->decimal}}));
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-rate').val(tax_rate[rowindex].toFixed({{$general_setting->decimal}}));
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.net_unit_price').val(net_unit_price.toFixed({{$general_setting->decimal}}));
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-value').val(tax.toFixed({{$general_setting->decimal}}));
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-price').text(sub_total_unit.toFixed({{$general_setting->decimal}}));
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sub-total').text((sub_total+topping_price).toFixed({{$general_setting->decimal}}));
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.subtotal-value').val((sub_total+topping_price).toFixed({{$general_setting->decimal}}));

    calculateTotal();
}

function calculateTotal() {
    //Sum of quantity
    var total_qty = 0;
    $("table.order-list tbody .qty").each(function(index) {
        if ($(this).val() == '') {
            total_qty += 0;
        } else {
            total_qty += parseFloat($(this).val());
        }
    });
    $("#total-qty").text(total_qty);
    $('input[name="total_qty"]').val(total_qty);

    //Sum of delivered quantity
    var total_dqty = 0;
    $("table.order-list tbody .dqty").each(function(index) {
        if ($(this).val() == '') {
            total_dqty += 0;
        } else {
            total_dqty += parseFloat($(this).val());
        }
    });
    $("#total-dqty").text(total_dqty);
    $("#total-dstatus").text(total_dqty + '/' + total_qty);


    //Sum of discount
    var total_discount = 0;
    $("table.order-list tbody .discount-value").each(function() {
        total_discount += parseFloat($(this).val());
    });
    $("#total-discount").text(total_discount.toFixed({{$general_setting->decimal}}));
    $('input[name="total_discount"]').val(total_discount.toFixed({{$general_setting->decimal}}));

    //Sum of tax
    var total_tax = 0;
    $(".tax-value").each(function() {
        total_tax += parseFloat($(this).val());
    });
    $("#total-tax").text(total_tax.toFixed({{$general_setting->decimal}}));
    $('input[name="total_tax"]').val(total_tax.toFixed({{$general_setting->decimal}}));

    //Sum of subtotal
    var total = 0;
    $(".sub-total").each(function() {
        total += parseFloat($(this).text());
    });
    $("#total").text(total.toFixed({{$general_setting->decimal}}));
    $('input[name="total_price"]').val(total.toFixed({{$general_setting->decimal}}));

    calculateGrandTotal();
}

function calculateGrandTotal() {
    var item = $('table.order-list tbody tr:last').index();
    if (item == -1) {
        $('#order-discount-val').val(0);
    }
    var total_qty = parseFloat($('input[name="total_qty"]').val());
    var subtotal = parseFloat($('input[name="total_price"]').val());
    var order_tax = parseFloat($('select[name="order_tax_rate"]').val());
    var order_discount_type = $('select[name="order_discount_type"]').val();
    var order_discount_value = parseFloat($('input[name="order_discount_value"]').val());

    if (!order_discount_value)
        order_discount_value = {{number_format(0, $general_setting->decimal, '.', '')}};

    if(order_discount_type == 'Flat') {
        if(!currencyChange) {
            var order_discount = parseFloat(order_discount_value);
        }
        else
            var order_discount = parseFloat(order_discount_value*currency['exchange_rate']);
    }
    else
        var order_discount = parseFloat(subtotal * (order_discount_value / 100));

    $("#discount").text(order_discount_value.toFixed({{$general_setting->decimal}}));
    $('input[name="order_discount"]').val(order_discount);
    $('#order-discount-val').val(order_discount_value);
    $('input[name="order_discount_type"]').val(order_discount_type);
    if(!currencyChange)
        var shipping_cost = parseFloat($('input[name="shipping_cost"]').val());
    else
        var shipping_cost = parseFloat($('input[name="shipping_cost"]').val() * currency['exchange_rate']);
    if (shipping_cost.length < 1)
        shipping_cost = {{number_format(0, $general_setting->decimal, '.', '')}};

    item = ++item + '(' + total_qty + ')';
    order_tax = (subtotal - order_discount) * (order_tax / 100);
    var grand_total = (subtotal + order_tax + shipping_cost) - order_discount;
    $('input[name="grand_total"]').val(grand_total.toFixed({{$general_setting->decimal}}));

    if(!currencyChange)
        var coupon_discount = parseFloat($('input[name="coupon_discount"]').val());
    else
        var coupon_discount = parseFloat($('input[name="coupon_discount"]').val() * currency['exchange_rate']);
    if (!coupon_discount)
        coupon_discount = {{number_format(0, $general_setting->decimal, '.', '')}};
    grand_total -= coupon_discount;

    $('#item').text(item);
    $('input[name="item"]').val($('table.order-list tbody tr:last').index() + 1);
    $('#subtotal').text(subtotal.toFixed({{$general_setting->decimal}}));
    $('#order_tax').text(order_tax.toFixed({{$general_setting->decimal}}));
    $('#tax').text(order_tax.toFixed({{$general_setting->decimal}}));
    $('input[name="order_tax"]').val(order_tax.toFixed({{$general_setting->decimal}}));
    $('#order_discount').text(order_discount.toFixed({{$general_setting->decimal}}));
    $('#shipping_cost').text(shipping_cost.toFixed({{$general_setting->decimal}}));
    $('input[name="shipping_cost"]').val(shipping_cost);
    $('#grand_total').text(grand_total.toFixed({{$general_setting->decimal}}));
    $('input[name="grand_total"]').val(grand_total.toFixed({{$general_setting->decimal}}));
    currencyChange = false;
}

function cancel(rownumber) {
    while(rownumber >= 0) {
        product_price.pop();
        wholesale_price.pop();
        product_discount.pop();
        tax_rate.pop();
        tax_name.pop();
        tax_method.pop();
        unit_name.pop();
        unit_operator.pop();
        unit_operation_value.pop();
        $('table.order-list tbody tr:last').remove();
        rownumber--;
    }
    $('input[name="shipping_cost"]').val('');
    $('input[name="order_discount_value"]').val('');
    $('select[name="order_tax_rate"]').val(0);
    calculateTotal();
}

$('select[name="order_discount_type"]').on("change", function() {
    calculateGrandTotal();
});

$('input[name="order_discount_value"]').on("input", function() {
    calculateGrandTotal();
});

$('input[name="shipping_cost"]').on("input", function() {
    calculateGrandTotal();
});

$('select[name="order_tax_rate"]').on("change", function() {
    calculateGrandTotal();
});

$(window).keydown(function(e){
    if (e.which == 13) {
        var $targ = $(e.target);
        if (!$targ.is("textarea") && !$targ.is(":button,:submit")) {
            var focusNext = false;
            $(this).find(":input:visible:not([disabled],[readonly]), a").each(function(){
                if (this === e.target) {
                    focusNext = true;
                }
                else if (focusNext){
                    $(this).focus();
                    return false;
                }
            });
            return false;
        }
    }
});

$('#payment-form').on('submit',function(e){
    var rownumber = $('table.order-list tbody tr:last').index();
    $("table.order-list tbody .qty").each(function(index) {
        if ($(this).val() == '') {
            alert(@json(__('db.One of products has no quantity!')));
            e.preventDefault();
        }
    });
    if (rownumber < 0) {
        alert(@json(__('db.Please insert product to order table!')))
        e.preventDefault();
    }
    else if(parseFloat($('input[name="total_qty"]').val()) <= 0) {
        alert(@json(__('db.Product quantity is 0')));
        e.preventDefault();
    }
    else {
        $("#submit-button").prop('disabled', true);
        $(".batch-no").prop('disabled', false);
    }
});

</script>
<script type="text/javascript" src="https://js.stripe.com/v3/"></script>
@endpush
