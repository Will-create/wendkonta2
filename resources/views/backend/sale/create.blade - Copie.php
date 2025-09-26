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
                        <h4>{{__('db.Add Sale')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'sales.store', 'method' => 'post', 'files' => true, 'class' => 'payment-form']) !!}
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.date')}}</label>
                                            <input type="text" name="created_at" class="form-control date" placeholder="{{ __('db.Choose date') }}" value="{{date($general_setting->date_format,strtotime('now'))}}"/>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                {{__('db.Reference No')}}
                                            </label>
                                            <input type="text" name="reference_no" class="form-control" />
                                        </div>
                                        <x-validation-error fieldName="reference_no" />
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.customer')}} *</label>
                                            <div class="input-group pos">
                                                @php
                                                  $deposit = [];
                                                  $points = [];
                                                  $customer_active = DB::table('permissions')
                                                  ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                                                  ->where([
                                                    ['permissions.name', 'customers-add'],
                                                    ['role_id', \Auth::user()->role_id] ])->first();

                                                    if($lims_pos_setting_data) {
                                                        $customer_id = $lims_pos_setting_data->customer_id;
                                                    }
                                                    else{
                                                        $customer_id = $lims_customer_list[0]->id;
                                                    }
                                                @endphp
                                                @if($customer_active)
                                                <select required name="customer_id" id="customer_id" class="selectpicker form-control" data-live-search="true" title="Select customer..." style="width: 100px">
                                                @foreach($lims_customer_list as $customer)
                                                    @php
                                                      $deposit[$customer->id] = $customer->deposit - $customer->expense;

                                                      $points[$customer->id] = $customer->points;
                                                    @endphp
                                                    <option value="{{$customer->id}}" data-type="{{$customer->type}}" @if($customer->id == $customer_id) selected @endif>{{$customer->name . ' (' . $customer->phone_number . ')'}}</option>
                                                @endforeach
                                                </select>
                                                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#addCustomer"><i class="dripicons-plus"></i></button>
                                                @else
                                                <select required name="customer_id" id="customer_id" class="selectpicker form-control" data-live-search="true" title="Select customer...">
                                                @foreach($lims_customer_list as $customer)
                                                    @php
                                                      $deposit[$customer->id] = $customer->deposit - $customer->expense;

                                                      $points[$customer->id] = $customer->points;
                                                    @endphp
                                                    <option value="{{$customer->id}}" data-type="{{$customer->type}}" @if($customer->id == $customer_id) selected @endif>{{$customer->name . ' (' . $customer->phone_number . ')'}}</option>
                                                @endforeach
                                                </select>
                                                @endif
                                                <x-validation-error fieldName="customer_id" />
                                            </div>
                                        </div>
                                    </div>
                                    @if(isset(auth()->user()->warehouse_id))
                                    <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{auth()->user()->warehouse_id}}" />
                                    @else
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Warehouse')}} *</label>
                                            @php
                                            if($lims_pos_setting_data) {
                                                $warehouse_id = $lims_pos_setting_data->warehouse_id;
                                            }
                                            else{
                                                $warehouse_id = $lims_warehouse_list[0]->id;
                                            }
                                            @endphp
                                            <select required name="warehouse_id" id="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select warehouse...">
                                                @foreach($lims_warehouse_list as $warehouse)
                                                <option value="{{$warehouse->id}}" @if($warehouse->id == $warehouse_id) selected @endif>{{$warehouse->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    @endif
                                    <x-validation-error fieldName="warehouse_id" />
                                    @if(isset(auth()->user()->biller_id))
                                    <input type="hidden" name="biller_id" id="biller_id" value="{{auth()->user()->biller_id}}" />
                                    @else
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Biller')}} *</label>
                                            @php
                                            if($lims_pos_setting_data) {
                                                $biller_id = $lims_pos_setting_data->biller_id;
                                            }
                                            else{
                                                $biller_id = $lims_biller_list[0]->id;
                                            }
                                            @endphp
                                            <select required id="biller_id" name="biller_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select Biller...">
                                                @foreach($lims_biller_list as $biller)
                                                <option value="{{$biller->id}}" @if($biller->id == $biller_id) selected @endif>{{$biller->name . ' (' . $biller->company_name . ')'}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>{{__('db.Currency')}} *</label>
                                            <select name="currency_id" id="currency" class="form-control selectpicker" data-toggle="tooltip" title="" data-original-title="Sale currency">
                                                @foreach($currency_list as $currency_data)
                                                <option value="{{$currency_data->id}}" data-rate="{{$currency_data->exchange_rate}}">{{$currency_data->code}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group mb-0">
                                            <label>{{__('db.Exchange Rate')}} *</label>
                                        </div>
                                        <div class="form-group d-flex">
                                            <input class="form-control" type="text" id="exchange_rate" name="exchange_rate" value="{{$currency->exchange_rate}}">
                                            <div class="input-group-append">
                                                <span class="input-group-text" data-toggle="tooltip" title="" data-original-title="currency exchange rate">i</span>
                                            </div>
                                        </div>
                                    </div>
                                    @if(in_array('restaurant',explode(',',$general_setting->modules)))
                                    <div class="col-md-3 col-6">
                                        <div class="form-group top-fields">
                                            <label>{{__('db.Service')}}</label>
                                            @if(!empty($service_id))
                                            <div class="input-group pos">
                                                <select required id="service_id" name="service_id" class="selectpicker form-control" title="Select service...">
                                                    <option value="1" @if($service_id == 1) selected @endif>{{__('db.Dine In')}}</option>
                                                    <option value="2" @if($service_id == 2) selected @endif>{{__('db.Take Away')}}</option>
                                                    <option value="3" @if($service_id == 3) selected @endif>{{__('db.Delivery')}}</option>
                                                </select>
                                            </div>
                                            @else
                                            <div class="input-group pos">
                                                <select required id="service_id" name="service_id" class="selectpicker form-control" title="Select service...">
                                                    <option value="1" selected>{{__('db.Dine In')}}</option>
                                                    <option value="2">{{__('db.Take Away')}}</option>
                                                    <option value="3">{{__('db.Delivery')}}</option>
                                                </select>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="form-group top-fields">
                                            <label>{{__('db.table')}}</label>
                                            <div class="input-group pos">
                                                <select required id="table_id" name="table_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select table...">
                                                    @foreach($lims_table_list as $table)
                                                    <option value="{{$table->id}}" @if(!empty($table_id) && $table->id == $table_id) selected @endif>
                                                        {{$table->name}} at {{$table->floor}} ( ðŸ‘¤ {{$table->number_of_person}})
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-6">
                                        <div class="form-group top-fields">
                                            <label>{{__('db.Waiter')}}</label>
                                            <div class="input-group pos">
                                                <select required id="waiter_id" name="waiter_id" class="selectpicker form-control" title="Select waiter...">
                                                    @if(auth()->user()->service_staff == 1)
                                                    <option value="{{auth()->user()->id}}" selected >{{auth()->user()->name}}</option>
                                                    @else
                                                        @foreach($waiter_list as $waiter)
                                                        <option value="{{$waiter->id}}" @if(!empty($waiter_id) && $waiter->id == $waiter_id) selected @endif>
                                                            {{$waiter->name}}
                                                        </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div> 
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <label>{{__('db.Select Product')}}</label>
                                        <div class="search-box form-group mb-2" style="position:relative">
                                            <div class="input-group pos">
                                                <input style="border: 1px solid #7c5cc4;" type="text" name="product_code_name" id="product-search-input" placeholder="Scan/Search product by name/code/IMEI" class="form-control" autofocus />
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
                                                        <th>{{__('db.product')}}</th>
                                                        <th>{{__('db.Quantity')}}</th>
                                                        <th>{{__('db.Delivered quantity')}}</th>
                                                        <th>{{__('db.Delivery Status')}}</th>
                                                        <th>{{__('db.Net Unit Price')}}</th>
                                                        <th>{{__('db.Discount')}}</th>
                                                        <th>{{__('db.Tax')}}</th>
                                                        <th>{{__('db.Subtotal')}}</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                                <tfoot class="tfoot active">
                                                    <th>{{__('db.Total')}}</th>
                                                    <th id="total-qty" class="text-center">0</th>
                                                    <th id="total-dqty" class="text-center">0</th>
                                                    <th id="total-dstatus" class="text-center">0/0</th>
                                                    <th></th>
                                                    <th id="total-discount">{{number_format(0, $general_setting->decimal, '.', '')}}</th>
                                                    <th id="total-tax">{{number_format(0, $general_setting->decimal, '.', '')}}</th>
                                                    <th id="total">{{number_format(0, $general_setting->decimal, '.', '')}}</th>
                                                    <th></th>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_qty" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_discount" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_tax" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_price" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="item" />
                                            <input type="hidden" name="order_tax" />
                                        </div>
                                        <x-validation-error fieldName="item" />
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="grand_total" />
                                            <input type="hidden" name="used_points" />
                                            <input type="hidden" name="coupon_active" value="0" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
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
                                            <select id="order-discount-type" name="order_discount_type" class="form-control">
                                              <option value="Flat">{{__('db.Flat')}}</option>
                                              <option value="Percentage">{{__('db.Percentage')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Order Discount Value')}}</label>
                                            <input type="text" name="order_discount_value" value="0" class="form-control numkey" id="order-discount-val">
                                            <input type="hidden" name="order_discount" class="form-control" id="order-discount">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                {{__('db.Shipping Cost')}}
                                            </label>
                                            <input type="number" name="shipping_cost" value="0" class="form-control" step="any"/>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Attach Document')}}</label> <i class="dripicons-question" data-toggle="tooltip" title="Only jpg, jpeg, png, gif, pdf, csv, docx, xlsx and txt file is supported"></i>
                                            <input type="file" name="document" class="form-control" />
                                            <x-validation-error fieldName="document" />
                                            <x-validation-error fieldName="extension" />
                                        </div>
                                    </div>
                                    @foreach($custom_fields as $field)
                                        @if(!$field->is_admin || \Auth::user()->role_id == 1)
                                            <div class="{{'col-md-'.$field->grid_value}}">
                                                <div class="form-group">
                                                    <label>{{$field->name}}</label>
                                                    @if($field->type == 'text')
                                                        <input type="text" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$field->default_value}}" class="form-control" @if($field->is_required){{'required'}}@endif>
                                                    @elseif($field->type == 'number')
                                                        <input type="number" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$field->default_value}}" class="form-control" @if($field->is_required){{'required'}}@endif>
                                                    @elseif($field->type == 'textarea')
                                                        <textarea rows="5" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$field->default_value}}" class="form-control" @if($field->is_required){{'required'}}@endif></textarea>
                                                    @elseif($field->type == 'checkbox')
                                                        <br>
                                                        <?php $option_values = explode(",", $field->option_value); ?>
                                                        @foreach($option_values as $value)
                                                            <label>
                                                                <input type="checkbox" name="{{str_replace(' ', '_', strtolower($field->name))}}[]" value="{{$value}}" @if($value == $field->default_value){{'checked'}}@endif @if($field->is_required){{'required'}}@endif> {{$value}}
                                                            </label>
                                                            &nbsp;
                                                        @endforeach
                                                    @elseif($field->type == 'radio_button')
                                                        <br>
                                                        <?php $option_values = explode(",", $field->option_value); ?>
                                                        @foreach($option_values as $value)
                                                            <label class="radio-inline">
                                                                <input type="radio" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$value}}" @if($value == $field->default_value){{'checked'}}@endif @if($field->is_required){{'required'}}@endif> {{$value}}
                                                            </label>
                                                            &nbsp;
                                                        @endforeach
                                                    @elseif($field->type == 'select')
                                                        <?php $option_values = explode(",", $field->option_value); ?>
                                                        <select class="form-control" name="{{str_replace(' ', '_', strtolower($field->name))}}" @if($field->is_required){{'required'}}@endif>
                                                            @foreach($option_values as $value)
                                                                <option value="{{$value}}" @if($value == $field->default_value){{'selected'}}@endif>{{$value}}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($field->type == 'multi_select')
                                                        <?php $option_values = explode(",", $field->option_value); ?>
                                                        <select class="form-control" name="{{str_replace(' ', '_', strtolower($field->name))}}[]" @if($field->is_required){{'required'}}@endif multiple>
                                                            @foreach($option_values as $value)
                                                                <option value="{{$value}}" @if($value == $field->default_value){{'selected'}}@endif>{{$value}}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($field->type == 'date_picker')
                                                        <input type="text" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$field->default_value}}" class="form-control date" @if($field->is_required){{'required'}}@endif>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Sale Status')}} *</label>
                                            <select name="sale_status" class="form-control">
                                                <option value="1">{{__('db.Completed')}}</option>
                                                <option value="2">{{__('db.Pending')}}</option>
                                                @if(in_array('restaurant',explode(',',$general_setting->modules)))
                                                <option value="5" selected>{{__('db.Processing')}}</option>
                                                @endif
                                            </select>
                                            <x-validation-error fieldName="sale_status" />
                                        </div>
                                    </div>
                                     
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{__('db.Payment Status')}} *</label>
                                            <select name="payment_status" id="payment_status" class="form-control">
                                                <option value="1">{{__('db.Pending')}}</option>
                                                <option value="2">{{__('db.Due')}}</option>
                                                <option value="3">{{__('db.Partial')}}</option>
                                                <option value="4">{{__('db.Paid')}}</option>
                                            </select>
                                            <x-validation-error fieldName="payment_status" />
                                        </div>
                                    </div>
                                    <?php
                                        $accountSelection = $role_has_permissions_list->where('name', 'account-selection')->first();
                                    ?>
                                    @if ($accountSelection)
                                        <!-- New Account Selection Field -->
                                        <div id="account-list" class="col-md-3 col-6" hidden>
                                            <div class="form-group top-fields">
                                                <label>{{__('db.Account')}}</label>
                                                <select required name="account_id" id="account_id" class="selectpicker form-control" data-live-search="true">
                                                    <option value="0" selected style="color: #A7B49E;">Select an Account</option>
                                                    @foreach($lims_account_list as $account)
                                                        <option value="{{$account->id}}">{{$account->name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div id="payment">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('db.Paid By')}}</label>
                                                <select name="paid_by_id[]" class="form-control">
                                                    @if(in_array("cash",$options))
                                                    <option value="1">{{ __('db.Cash') }}</option>
                                                    @endif
                                                    @if(in_array("gift_card",$options))
                                                    <option value="2">{{ __('db.Gift Card') }}</option>
                                                    @endif
                                                    @if(in_array("card",$options))
                                                    <option value="3">{{ __('db.Credit Card') }}</option>
                                                    @endif
                                                    @if(in_array("cheque",$options))
                                                    <option value="4">{{ __('db.Cheque') }}</option>
                                                    @endif
                                                    @if(in_array("paypal",$options) && (strlen($lims_pos_setting_data->paypal_live_api_username)>0) && (strlen($lims_pos_setting_data->paypal_live_api_password)>0) && (strlen($lims_pos_setting_data->paypal_live_api_secret)>0))
                                                    <option value="5">{{ __('db.Paypal') }}</option>
                                                    @endif
                                                    @if(in_array("deposit",$options))
                                                    <option value="6">{{ __('db.Deposit') }}</option>
                                                    @endif
                                                    @if($lims_reward_point_setting_data && $lims_reward_point_setting_data->is_active)
                                                    <option value="7">{{ __('db.Points') }}</option>
                                                    @endif
                                                    @if(in_array("pesapal",$options))
                                                    <option value="8">{{ __('db.Pesapal') }}</option>
                                                    @endif
                                                    @foreach($options as $option)
                                                        @if($option !== 'cash' && $option !== 'card' && $option !== 'card' && $option !== 'cheque' && $option !== 'gift_card' && $option !== 'deposit' && $option !== 'paypal' && $option !== 'pesapal')
                                                            <option value="{{$option}}">{{ucfirst($option)}}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('db.Recieved Amount')}} *</label>
                                                <input type="number" name="paying_amount[]" class="form-control" id="paying-amount" step="any" />
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('db.Paying Amount')}} *</label>
                                                <input type="number" name="paid_amount[]" class="form-control" id="paid-amount" step="any"/>
                                            </div>
                                            <div class="alert alert-danger d-none p-2 position-absolute" id="paying-amount-error">
                                                Paying amount must be greater than 0
                                            </div>

                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('db.Payment Receiver')}}</label>
                                                <input type="text" name="payment_receiver" class="form-control" id="payment-receiver"/>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('db.Change')}}</label>
                                                <p id="change" class="ml-2">{{number_format(0, $general_setting->decimal, '.', '')}}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <div class="card-element" class="form-control">
                                                </div>
                                                <div class="card-errors" role="alert"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row" id="gift-card">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label> {{__('db.Gift Card')}} *</label>
                                                <select id="gift_card_id" name="gift_card_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select Gift Card..."></select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row" id="cheque">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>{{__('db.Cheque Number')}} *</label>
                                                <input type="text" name="cheque_no" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label>{{__('db.Payment Note')}}</label>
                                            <textarea rows="3" class="form-control" name="payment_note"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{__('db.Sale Note')}}</label>
                                            <textarea rows="25" class="form-control" name="sale_note"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{__('db.Staff Note')}}</label>
                                            <textarea rows="25" class="form-control" name="staff_note"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <input type="hidden" name="draft" value="0" />
                                    <button id="submit-button" type="button" class="btn btn-primary">{{__('db.submit')}}</button>
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
                            <div class="col-md-4 form-group">
                                <label>{{__('db.Cost')}}</label>
                                <p id="product-cost"></p>
                            </div>
                        </div>
                        <button type="button" name="update_btn" class="btn btn-primary">{{__('db.update')}}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- add customer modal -->
    <div id="addCustomer" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
          <div class="modal-content">
            {!! Form::open(['route' => 'customer.store', 'method' => 'post', 'files' => true, 'id' => 'customer-form']) !!}
            <div class="modal-header">
              <h5 id="exampleModalLabel" class="modal-title">{{__('db.Add Customer')}}</h5>
              <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
              <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                <div class="form-group">
                    <label>{{__('db.Customer Group')}} *</strong> </label>
                    <select required class="form-control selectpicker" name="customer_group_id">
                        @foreach($lims_customer_group_all as $customer_group)
                            <option value="{{$customer_group->id}}">{{$customer_group->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{__('db.name')}} *</strong> </label>
                    <input type="text" name="customer_name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>{{__('db.Email')}}</label>
                    <input type="text" name="email" placeholder="example@example.com" class="form-control">
                </div>
                <div class="form-group">
                    <label>{{__('db.Phone Number')}} *</label>
                    <input type="text" name="phone_number" required class="form-control">
                </div>
                <div class="form-group">
                    <label>{{__('db.Address')}}</label>
                    <input type="text" name="address" class="form-control">
                </div>
                <div class="form-group">
                    <label>{{__('db.City')}}</label>
                    <input type="text" name="city" class="form-control">
                </div>
                <div class="form-group">
                    <input type="hidden" name="pos" value="1">
                    <button type="button" class="btn btn-primary customer-submit-btn">{{__('db.submit')}}</button>
                </div>
            </div>
            {{ Form::close() }}
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
                            product.dqty = 1;
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
                                                            data-dqty="${product.dqty}" 
                                                            data-dstatus="${product.dqty} / ${product.qty}" 
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
                                                                            data-dqty="${product.dqty}" 
                                                                            data-dstatus="${product.dqty} / ${product.qty}" 
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
                                                            data-dqty="${product.dqty}" 
                                                            data-dstatus="${product.dqty} / ${product.qty}" 
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

    @if($lims_pos_setting_data)
        var public_key = <?php echo json_encode($lims_pos_setting_data->stripe_public_key) ?>;
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

    $('.customer-submit-btn').on("click", function() {
        $.ajax({
            type:'POST',
            url:'{{route('customer.store')}}',
            data: $("#customer-form").serialize(),
            success:function(response) {
                key = response['id'];
                value = response['name']+' ['+response['phone_number']+']';
                $('select[name="customer_id"]').append('<option value="'+ key +'">'+ value +'</option>');
                $('select[name="customer_id"]').val(key);
                $('.selectpicker').selectpicker('refresh');
                $("#addCustomer").modal('hide');
            }
        });
    });

    function setCustomerGroupRate(id) {
        $.get('{{ url("sales/getcustomergroup") }}/' + id, function(data) {
            customer_group_rate = (data / 100);
        });
    }

$(window).on('load', async function () {

    var customer_id = $('#customer_id').val();
    setCustomerGroupRate(customer_id);
});

$("#payment").hide();
$(".card-element").hide();
$("#gift-card").hide();
$("#cheque").hide();

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

var deposit = <?php echo json_encode($deposit) ?>;
var points = <?php echo json_encode($points) ?>;

@if($lims_reward_point_setting_data)
var reward_point_setting = <?php echo json_encode($lims_reward_point_setting_data) ?>;
@endif

var rowindex;
var customer_group_rate;
var row_product_price;
var pos;
var role_id = <?php echo json_encode(Auth::user()->role_id)?>;

var warehouse_id = $('#warehouse_id').val();

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
});


//Change delivered quantity
$("#myTable").on('input', '.dqty', function() {
    rowindex = $(this).closest('tr').index();

    if($(this).val() < 0 && $(this).val() != '') {
      $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val(1);
      alert("Quantity can't be less than 0");
    }
    if(is_variant[rowindex])
        checkQuantity2($(this).val(), true);
    else
        checkDiscount($(this).val(), true);
});


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
        alert('Invalid Discount Input!');
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
    //checkQuantity(edit_qty, false);
    $('#editModal').modal('hide');
});

$("#myTable").on('click', '.plus', function() {
    rowindex = $(this).closest('tr').index();
    var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val();
    var max_qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').attr('max');
    if(!qty)
    qty = 1;
    // if(max_qty && parseFloat(qty) >= parseFloat(max_qty)) {
    //     alert("Maximum quantity reached!");
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



// Delivered products
$("#myTable").on('click', '.dplus', function() {
    rowindex = $(this).closest('tr').index();
    var dqty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val();
    var max_dqty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').attr('max');
    if(!dqty)
    dqty = 1;
    // if(max_dqty && parseFloat(dqty) >= parseFloat(max_dqty)) {
    //     alert("Maximum quantity reached!");
    //     return;
    // }
    else
    dqty = parseFloat(dqty) + 1;
    if(is_variant[rowindex]){
        checkQuantity2(String(dqty), true);
    }else{
        checkDiscount(dqty, true);
    }
});

$("#myTable").on('click', '.dminus', function() {
    rowindex = $(this).closest('tr').index();
    var dqty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val()) - 1;
    if (dqty > 0) {
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .dqty').val(dqty);

        if(is_variant[rowindex])
            checkQuantity2(String(dqty), true);
        else
            checkDiscount(dqty, '3');
    }
    else {
        dqty = 1;
    }

});
$("select[name=price_option]").on("change", function () {
    $("#editModal input[name=edit_unit_price]").val($(this).val());
});

$("#myTable").on("change", ".batch-no", function () {
    rowindex = $(this).closest('tr').index();
    var product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-id').val();
    var warehouse_id = $('#warehouse_id').val();
    $.get('../check-batch-availability/' + product_id + '/' + $(this).val() + '/' + warehouse_id, function(data) {
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

    if(!customer_id)
        alert('Please select Customer!');
    else if(!warehouse_id)
        alert('Please select Warehouse!');
    else if(!biller_id)
        alert('Please select Biller!');
    @if(in_array('restaurant',explode(',',$general_setting->modules)))
    else if(!table_id && service_id == 1){
        alert('Please select Table!');
    }
    else if(!waiter_id && service_id == 1){
        alert('Please select Waiter!');
    }
    @endif
    else{
        var data = $(this).data();
        productSearch(data);
    }
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
                    alert('Same imei or serial number is not allowed!');
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
            dqty: data.dqty,
            dstatus: data.dstatus,
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
    console.log(data);
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

    // Quantity
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

    // Delivered quantity
    cols += '<td><div class="input-group"><span class="input-group-btn">';

    // If no IMEI, show minus button
    if (!data[18] || data[18] == 'null') {
        cols += '<button type="button" class="btn btn-default dminus mr-1" style="padding:5px 8px"><i class="dripicons-minus"></i></button></span>';
    }

    // Input field
    cols += '<input type="text" name="dqty[]" class="form-control dqty numkey input-number" style="font-size:13px;max-width:50px;padding: 0 0;text-align:center" step="any" value="'+data[23]+'" max="'+data[15].toString().trim()+'" required><span class="input-group-btn">';

    // If no IMEI, show plus button
    if (!data[18] || data[18] == 'null') {
        cols += '<button type="button" class="btn btn-default dplus ml-1" style="padding:5px 8px"><i class="dripicons-plus"></i></button>';
    }

    cols += '</span></div></td>' ;

    // Ratio
    cols += '<td class="dstatus">' + data[24];
    cols += '</td>';

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
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
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
    checkQuantity(String(qty), 0, flag);
}

let is_checking = false;


function checkQuantity(sale_qty, flag) {

    if (is_checking)
        return;

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
                is_checking = true;
                alert('Quantity exceeds stock quantity!');
                if (flag) {
                    sale_qty = parseFloat(qty);
                    is_checking = false;
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

function checkQuantity2(sale_qty, flag) {
    if (is_checking)
        return;


    var row_product_code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-code').val();
    var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.dqty').attr('max');
    var product_type = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_type').val();


    if (sale_qty > parseFloat(qty)) {
        is_checking = true;
        alert('Quantity exceeds sales quantity!');
        if (flag) {
            sale_qty = parseFloat(qty);
            is_checking = false;
            checkQuantity(sale_qty, true);
        }
        else {
            edit();
            return;
        }
    }

    //    calculateRowProductData(sale_qty);
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

$('select[name="payment_status"]').on("change", function() {
    var payment_status = $(this).val();
    if (payment_status == 3 || payment_status == 4) {
        $("#paid-amount").prop('disabled',false);
        $("#payment").show();
        $("#paying-amount").prop('required',true);
        $("#paid-amount").prop('required',true);
        if(payment_status == 4){
            $("#paid-amount").prop('disabled',true);
            $('input[name="paying_amount[]"]').val($('input[name="grand_total"]').val());
            $('input[name="paid_amount[]"]').val($('input[name="grand_total"]').val());
        }
        $("#account-list").attr("hidden", false);
    }
    else{
        $("#paying-amount").prop('required',false);
        $("#paid-amount").prop('required',false);
        $('input[name="paying_amount[]"]').val('');
        $('input[name="paid_amount[]"]').val('');
        $("#payment").hide();
        $("#account-list").attr("hidden", true);
    }
});

$('select[name="paid_by_id[]"]').on("change", function() {
    var id = $(this).val();
    $(".payment-form").off("submit");
    $('input[name="cheque_no"]').attr('required', false);
    $('select[name="gift_card_id"]').attr('required', false);
    if(id == 2) {
        $("#gift-card").show();
        $.ajax({
            url: 'get_gift_card',
            type: "GET",
            dataType: "json",
            success:function(data) {
                $('select[name="gift_card_id"]').empty();
                $.each(data, function(index) {
                    gift_card_amount[data[index]['id']] = data[index]['amount'];
                    gift_card_expense[data[index]['id']] = data[index]['expense'];
                    $('select[name="gift_card_id"]').append('<option value="'+ data[index]['id'] +'">'+ data[index]['card_no'] +'</option>');
                });
                $('.selectpicker').selectpicker('refresh');
            }
        });
        $(".card-element").hide();
        $("#cheque").hide();
        $('select[name="gift_card_id"]').attr('required', true);
    }
    else if (id == 3) {
        @if($lims_pos_setting_data && (strlen($lims_pos_setting_data->stripe_public_key)>0) && (strlen($lims_pos_setting_data->stripe_secret_key )>0))
            $.getScript( "../vendor/stripe/checkout.js" );
            $(".card-element").show();
            $(".card-errors").show();
        @endif
        $("#gift-card").hide();
        $("#cheque").hide();
    }
    else if (id == 4) {
        $("#cheque").show();
        $("#gift-card").hide();
        $(".card-element").hide();
        $('input[name="cheque_no"]').attr('required', true);
    }
    else {
        $("#gift-card").hide();
        $(".card-element").hide();
        $("#cheque").hide();
        if (id == 6) {
            if($('input[name="paid_amount[]"]').val() > deposit[$('#customer_id').val()]){
                alert('Amount exceeds customer deposit! Customer deposit : '+ deposit[$('#customer_id').val()]);
            }
        }
        else if (id == 7) {
            pointCalculation();
        }
    }
});

function pointCalculation() {
    paid_amount = $('input[name="paid_amount[]"]').val();
    required_point = Math.ceil(paid_amount / reward_point_setting['per_point_amount']);
    if(required_point > points[$('#customer_id').val()]) {
        alert('Customer does not have sufficient points. Available points: '+points[$('#customer_id').val()]);
    }
    else {
        $("input[name='used_points']").val(required_point);
    }
}

$('select[name="gift_card_id"]').on("change", function() {
    var balance = gift_card_amount[$(this).val()] - gift_card_expense[$(this).val()];
    if($('input[name="paid_amount[]"]').val() > balance){
        alert('Amount exceeds card balance! Gift Card balance: '+ balance);
    }
});

$('input[name="paid_amount[]"]').on("input", function() {
    if( $(this).val() > parseFloat($('input[name="paying_amount[]"]').val()) ) {
        $('#paying-amount-error').addClass('d-none');
        $("#submit-button").prop('disabled', true).css('cursor', 'default');
        alert('Paying amount cannot be bigger than recieved amount');
        // $(this).val('');
    }
    else if( $(this).val() > parseFloat($('#grand_total').text()) ){
        $('#paying-amount-error').addClass('d-none');
        $("#submit-button").prop('disabled', true).css('cursor', 'default');
        alert('Paying amount cannot be bigger than grand total');
        // $(this).val('');
    } else if ($(this).val() <= 0) {
        $("#submit-button").prop('disabled', true).css('cursor', 'default');
        $('#paying-amount-error').removeClass('d-none');
    } else {
        $('#paying-amount-error').addClass('d-none');
        $("#submit-button").prop('disabled', false).css('cursor', 'pointer');
    }

    $("#change").text( parseFloat($("#paying-amount").val() - $(this).val()).toFixed({{$general_setting->decimal}}) );
    var id = $('select[name="paid_by_id[]"]').val();
    if(id == 2){
        var balance = gift_card_amount[$("#gift_card_id").val()] - gift_card_expense[$("#gift_card_id").val()];
        if($(this).val() > balance)
            alert('Amount exceeds card balance! Gift Card balance: '+ balance);
    }
    else if(id == 6){
        if( $('input[name="paid_amount[]"]').val() > deposit[$('#customer_id').val()] )
            alert('Amount exceeds customer deposit! Customer deposit : '+ deposit[$('#customer_id').val()]);
    }
});

$('input[name="paying_amount[]"]').on("input", function() {
    $("#change").text( parseFloat( $(this).val() - $("#paid-amount").val()).toFixed({{$general_setting->decimal}}));
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

$("#submit-button").on("click", function() {
    $('.payment-form').submit();
});

$(document).on('submit', '.payment-form', function(e) {
    let customer_type = $('#customer_id option:selected').data('type');
    let current_payment_status = parseInt($('select[name="payment_status"]').val());

    var rownumber = $('table.order-list tbody tr:last').index();
    $("table.order-list tbody .qty").each(function(index) {
        if ($(this).val() == '') {
            alert('One of products has no quantity!');
            e.preventDefault();
        }
    });

    if (customer_type === 'walkin' && current_payment_status !== 4) {
        alert('Payment Status should be Paid for Walk in Customer!');
        e.preventDefault();
    }
    else if ( rownumber < 0 ) {
        alert("Please insert product to order table!")
        e.preventDefault();
    }
    else if(parseFloat($('input[name="total_qty"]').val()) <= 0) {
        alert('Product quantity is 0');
        e.preventDefault();
    }
    else if( parseFloat($("#paying-amount").val()) < parseFloat($("#paid-amount").val()) ){
        alert('Paying amount cannot be bigger than recieved amount');
        e.preventDefault();
    }
    else if( $('select[name="payment_status"]').val() == 3 && parseFloat($("#paid-amount").val()) == parseFloat($('input[name="grand_total"]').val()) ) {
        alert('Paying amount equals to grand total! Please change payment status.');
        e.preventDefault();
    }
    else if(!$('#biller_id').val()) {
        alert('Please select a biller');
        e.preventDefault();
    }
    else {
        $("#submit-button").prop('disabled', true);
        $("#paid-amount").prop('disabled',false);
        $(".batch-no").prop('disabled', false);

        e.preventDefault(); // Prevents the default form submission behavior
        $.ajax({
            url: $('.payment-form').attr('action'),
            type: $('.payment-form').attr('method'),
            data: $('.payment-form').serialize(),
            success: function(response) {

                if (response.payment_method === 'pesapal' && response.redirect_url) {
                    // Redirect to the URL returned for Pesapal payment method
                    location.href = response.redirect_url;
                }else if(response.payment_method === 'moneipoint'){
                }else if ($('select[name="sale_status"]').val() == 1 && response !== 'pesapal') {
                    let link = "{{url('sales/gen_invoice/')}}" + '/' + response;
                    location.href = link;
                }
                else if($('select[name="sale_status"]').val() != 1){
                    localStorage.clear();
                    location.href = "{{route('sales.index')}}";
                }
                else {
                    localStorage.clear();
                    location.href = response;
                }
            },
            error: function(xhr) {
                console.log('Form submission failed.');
            }
        });

    }
});
</script>
<script type="text/javascript" src="https://js.stripe.com/v3/"></script>
@endpush
