<!DOCTYPE html>
<html>
@php
    $show = json_decode($invoice_settings->show_column);
@endphp

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" type="image/png" href="{{ url('logo', $general_setting->site_logo) }}" />
    <title>{{ $general_setting->site_title }}</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="all,follow">

    <style type="text/css">
        * {
            font-size: 14px;
            line-height: 24px;
            font-family: 'Ubuntu', sans-serif;
            text-transform: capitalize;
        }

        .btn {
            padding: 7px 10px;
            text-decoration: none;
            border: none;
            display: block;
            text-align: center;
            margin: 7px;
            cursor: pointer;
        }

        .btn-info {
            background-color: #999;
            color: #FFF;
        }

        .btn-primary {
            background-color: #6449e7;
            color: #FFF;
            width: 100%;
        }

        td,
        th,
        tr,
        table {
            border-collapse: collapse;
        }

        tr {
            border-bottom: 1px dotted #999;
        }

        td,
        th {
            padding: 7px 0;
            width: 50%;
        }

        table {
            width: 100%;
        }

        tfoot tr th:first-child {
            text-align: left;
        }

        .centered {
            text-align: center;
            align-content: center;
        }

        small {
            font-size: 11px;
        }

        @media print {
            * {
                font-size: 12px;
                line-height: 20px;
            }

            td,
            th {
                padding: 5px 0;
            }

            .hidden-print {
                display: none !important;
            }

            @page {
                margin: 1.5cm 0.5cm 0.5cm;
            }

            @page: first {
                margin-top: 0.5cm;
            }

            /*tbody::after {
                content: ''; display: block;
                page-break-after: always;
                page-break-inside: avoid;
                page-break-before: avoid;
            }*/
        }
    </style>
</head>

<body>

    <div style="max-width:290px;margin:0 auto">
        @if (preg_match('~[0-9]~', url()->previous()))
            @php $url = '../../pos'; @endphp
        @else
            @php $url = url()->previous(); @endphp
        @endif
        <div class="hidden-print">
            <table>
                <tr>
                    <td><a href="{{ $url }}" class="btn btn-info"><i class="fa fa-arrow-left"></i>
                            {{ __('db.Back') }}</a> </td>
                    <td><button onclick="window.print();" class="btn btn-primary"><i class="dripicons-print"></i>
                            {{ __('db.Print') }}</button></td>
                </tr>
            </table>
            <br>
        </div>


        <div id="receipt-data">
            @if (isset($show->show_warehouse_info) && $show->show_warehouse_info == 1)
                <div class="centered">
                    
                    @if ($general_setting->site_logo || $invoice_settings->company_logo)
                    <img src="{{ $invoice_settings->company_logo ? url('invoices', $invoice_settings->company_logo) : url('logo', $general_setting->site_logo) }}"
                            height="{{ $invoice_settings->logo_height ?? auto }}" width="{{ $invoice_settings->logo_width ?? auto }}" style="margin:5px 0;">
                    @endif

                    <h2 style="margin: 0 0 5px">{{ $general_setting->company_name ?? $lims_biller_data->company_name }}</h2>

                    <p style="margin: 0 0 5px">{{ __('db.Address') }} : {{ $lims_warehouse_data->address }}
                        <br>{{ __('db.Phone Number') }}: {{ $lims_warehouse_data->phone }}
                        {{-- @if ($general_setting->vat_registration_number)
                        <br>{{__('db.VAT Number')}}: {{$general_setting->vat_registration_number}}
                        @endif --}}
                    </p>
                </div>
            @endif
            <p>{{ __('db.date') }}:
                @if (isset($show->active_date_format) && $show->active_date_format == 1)
                {{ Carbon\Carbon::parse($lims_sale_data->created_at)->format($invoice_settings->invoice_date_format) }}
                @else
                    {{ $lims_sale_data->created_at }}
                @endif               
                <br>
                @if (isset($show->show_ref_number) && $show->show_ref_number == 1)
                {{ __('db.reference') }}: {{ $lims_sale_data->reference_no }}<br>
                @endif

                @if (isset($show->show_customer_name) && $show->show_customer_name == 1)
                {{ __('db.customer') }}: {{ $lims_customer_data->name }}
                @endif

                @if ($lims_sale_data->table_id)
                    <br>{{ __('db.Table') }}: {{ $lims_sale_data->table->name }}
                    <br>{{ __('db.Queue') }}: {{ $lims_sale_data->queue }}
                @endif
                <?php
                foreach ($sale_custom_fields as $key => $fieldName) {
                    $field_name = str_replace(' ', '_', strtolower($fieldName));
                    echo '<br>' . $fieldName . ': ' . $lims_sale_data->$field_name;
                }
                foreach ($customer_custom_fields as $key => $fieldName) {
                    $field_name = str_replace(' ', '_', strtolower($fieldName));
                    echo '<br>' . $fieldName . ': ' . $lims_customer_data->$field_name;
                }
                ?>

            </p>
            <table class="table-data">
                <tbody>
                    <?php $total_product_tax = 0; ?>
                    @foreach ($lims_product_sale_data as $key => $product_sale_data)
                        <?php
                        $lims_product_data = \App\Models\Product::find($product_sale_data->product_id);
                        if ($product_sale_data->variant_id) {
                            $variant_data = \App\Models\Variant::find($product_sale_data->variant_id);
                            $product_name = $lims_product_data->name . ' [' . $variant_data->name . ']';
                        } elseif ($product_sale_data->product_batch_id) {
                            $product_batch_data = \App\Models\ProductBatch::select('batch_no')->find($product_sale_data->product_batch_id);
                            $product_name = $lims_product_data->name . ' [' . __('db.Batch No') . ':' . $product_batch_data->batch_no . ']';
                        } else {
                            $product_name = $lims_product_data->name;
                        }
                        // @dd($product_sale_data->imei_number);
                        if ($product_sale_data->imei_number && !str_contains($product_sale_data->imei_number, 'null')) {
                            $product_name .= '<br><small>' . trans('IMEI or Serial Numbers') . ': ' . $product_sale_data->imei_number .'</small>';
                        }

                        // Warranty
                        if (isset($product_sale_data->warranty_duration)) {
                            $product_name .= '<br>' . "<span style='font-weight: bold;'>Warranty</span>: " . $product_sale_data->warranty_duration;
                            $product_name .= '<br>' . "<span style='font-weight: bold;'>Will Expire</span>: " . $product_sale_data->warranty_end;
                        }
                        // Guarantee
                        if (isset($product_sale_data->guarantee_duration)) {
                            $product_name .= '<br>' . "<span style='font-weight: bold;'>Guarantee</span>: " . $product_sale_data->guarantee_duration;
                            $product_name .= '<br>' . "<span style='font-weight: bold;'>Will Expire</span>: " . $product_sale_data->guarantee_end;
                        }

                        $topping_names = [];
                        $topping_prices = [];
                        $topping_price_sum = 0;

                        if ($product_sale_data->topping_id) {
                            $decoded_topping_id = is_string($product_sale_data->topping_id) ? json_decode($product_sale_data->topping_id, true) : $product_sale_data->topping_id;

                            //dd(json_decode($product_sale_data->topping_id));
                            if (is_array($decoded_topping_id)) {
                                foreach ($decoded_topping_id as $topping) {
                                    $topping_names[] = $topping['name']; // Extract name
                                    $topping_prices[] = $topping['price']; // Extract price
                                    $topping_price_sum += $topping['price']; // Sum up prices
                                }
                            }
                        }

                        $net_price_with_toppings = $product_sale_data->net_unit_price + $topping_price_sum;
                        $subtotal = $product_sale_data->total + $topping_price_sum;
                        ?>
                        @if (isset($show->show_description) && $show->show_description == 1 )
                            <tr style="border-top: 1px dotted #999">
                                <td colspan="2">
                                    {!! $product_name !!}

                                    @if (!empty($topping_names))
                                        <br><small>({{ implode(', ', $topping_names) }})</small>
                                    @endif

                                    @foreach ($product_custom_fields as $index => $fieldName)
                                        <?php $field_name = str_replace(' ', '_', strtolower($fieldName)); ?>
                                        @if ($lims_product_data->$field_name)
                                            @if (!$index)
                                                <br>{{ $fieldName . ': ' . $lims_product_data->$field_name }}
                                            @else
                                                {{ '/' . $fieldName . ': ' . $lims_product_data->$field_name }}
                                            @endif
                                        @endif
                                    @endforeach
                                    <br>{{ $product_sale_data->qty }} x
                                    {{ number_format((float) ($product_sale_data->total / $product_sale_data->qty), $general_setting->decimal, '.', ',') }}

                                    @if (!empty($topping_prices))
                                        <small>+
                                            {{ implode(' + ', array_map(fn($price) => number_format($price, $general_setting->decimal, '.', ','), $topping_prices)) }}</small>
                                    @endif

                                    @if ($product_sale_data->tax_rate)
                                        <?php $total_product_tax += $product_sale_data->tax; ?>
                                        [{{ __('db.Tax') }} ({{ $product_sale_data->tax_rate }}%):
                                        {{ $product_sale_data->tax }}]
                                    @endif
                                </td>
                                <td style="text-align:right;vertical-align:bottom">
                                    {{ number_format($subtotal, $general_setting->decimal, '.', ',') }}</td>
                            </tr>
                        @endif
                    @endforeach

                    <!-- <tfoot> -->
                    <tr>
                        <th colspan="2" style="text-align:left">{{ __('db.Total') }}</th>
                        <th style="text-align:right">
                            {{ number_format((float) $lims_sale_data->total_price, $general_setting->decimal, '.', ',') }}
                        </th>
                    </tr>
                    @if ($general_setting->invoice_format == 'gst' && $general_setting->state == 1)
                        <tr>
                            <td colspan="2">IGST</td>
                            <td style="text-align:right">
                                {{ number_format((float) $total_product_tax, $general_setting->decimal, '.', ',') }}
                            </td>
                        </tr>
                    @elseif($general_setting->invoice_format == 'gst' && $general_setting->state == 2)
                        <tr>
                            <td colspan="2">SGST</td>
                            <td style="text-align:right">
                                {{ number_format((float) ($total_product_tax / 2), $general_setting->decimal, '.', ',') }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">CGST</td>
                            <td style="text-align:right">
                                {{ number_format((float) ($total_product_tax / 2), $general_setting->decimal, '.', ',') }}
                            </td>
                        </tr>
                    @endif
                    @if ($lims_sale_data->order_tax)
                        <tr>
                            <th colspan="2" style="text-align:left">{{ __('db.Order Tax') }}</th>
                            <th style="text-align:right">
                                {{ number_format((float) $lims_sale_data->order_tax, $general_setting->decimal, '.', ',') }}
                            </th>
                        </tr>
                    @endif
                    @if ($lims_sale_data->order_discount)
                        <tr>
                            <th colspan="2" style="text-align:left">{{ __('db.Order Discount') }}</th>
                            <th style="text-align:right">
                                {{ number_format((float) $lims_sale_data->order_discount, $general_setting->decimal, '.', ',') }}
                            </th>
                        </tr>
                    @endif
                    @if ($lims_sale_data->coupon_discount)
                        <tr>
                            <th colspan="2" style="text-align:left">{{ __('db.Coupon Discount') }}</th>
                            <th style="text-align:right">
                                {{ number_format((float) $lims_sale_data->coupon_discount, $general_setting->decimal, '.', ',') }}
                            </th>
                        </tr>
                    @endif
                    @if ($lims_sale_data->shipping_cost)
                        <tr>
                            <th colspan="2" style="text-align:left">{{ __('db.Shipping Cost') }}</th>
                            <th style="text-align:right">
                                {{ number_format((float) $lims_sale_data->shipping_cost, $general_setting->decimal, '.', ',') }}
                            </th>
                        </tr>
                    @endif
                    <tr>
                        <th colspan="2" style="text-align:left">{{ __('db.grand total') }}</th>
                        <th style="text-align:right">
                            {{ number_format((float) $lims_sale_data->grand_total, $general_setting->decimal, '.', ',') }}
                        </th>
                    </tr>
                    @if ($lims_sale_data->grand_total - $lims_sale_data->paid_amount > 0)
                        <tr>
                            <th colspan="2" style="text-align:left">{{ __('db.Due') }}</th>
                            <th style="text-align:right">
                                {{ number_format((float) ($lims_sale_data->grand_total - $lims_sale_data->paid_amount), $general_setting->decimal, '.', ',') }}
                            </th>
                        </tr>
                    @endif
                    @if ($totalDue && isset($show->hide_total_due))
                        <tr>
                            @if (!$show->hide_total_due)
                            <th colspan="2" style="text-align:left">{{ __('db.Total Due') }}</th>
                            <th style="text-align:right">
                                {{ number_format($totalDue, $general_setting->decimal, '.', ',') }}</th>
                            @endif
                        </tr>
                    @endif
                    <tr>
                        @if (isset($show->show_in_words) && $show->show_in_words == 1)
                            @if ($general_setting->currency_position == 'prefix')
                                <th class="centered" colspan="3">{{ __('db.In Words') }}:
                                    <span>{{ $currency_code }}</span>
                                    <span>{{ str_replace('-', ' ', $numberInWords) }}</span>
                                </th>
                            @else
                                <th class="centered" colspan="3">{{ __('db.In Words') }}:
                                    <span>{{ str_replace('-', ' ', $numberInWords) }}</span>
                                    <span>{{ $currency_code }}</span>
                                </th>
                            @endif
                        @endif
                    </tr>
                </tbody>
                <!-- </tfoot> -->
            </table>
            <table>
                <tbody>
                    @if (isset($show->show_paid_info) && $show->show_paid_info == 1)
                        @foreach ($lims_payment_data as $payment_data)
                            <tr style="background-color:#ddd;">
                                <td style="padding: 5px;width:30%">{{ __('db.Paid By') }}:
                                    {{ $payment_data->paying_method }}</td>
                                <td style="padding: 5px;width:40%">{{ __('db.Amount') }}:
                                    {{ number_format((float) ($payment_data->amount + $payment_data->change), $general_setting->decimal, '.', ',') }}
                                </td>
                                <td style="padding: 5px;width:30%">{{ __('db.Change') }}:
                                    {{ number_format((float) $payment_data->change, $general_setting->decimal, '.', ',') }}
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td class="centered" colspan="3">
                            <small>
                                @if (isset($show->show_biller_info) && $show->show_biller_info == 1)
                                {{ __('db.Served By') }}: {{ $lims_bill_by['name'] }} - ({{ $lims_bill_by['user_name'] }})
                                @endif
                            </small><br>
                            @if (isset($show->show_footer_text) && $show->show_footer_text == 1)
                                <strong>{{ $invoice_settings->footer_text ?? __('db.Thank you for shopping with us Please come again') }}</strong>
                            @endif
                    </tr>
                    <tr>
                        <td class="centered" colspan="3">
                            @if (isset($show->show_barcode) && $show->show_barcode == 1)
                                <?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS1D::getBarcodePNG($lims_sale_data->reference_no, 'C128') . '" width="300" alt="barcode"   />'; ?>
                            @endif
                            <br>
                            @if (isset($show->show_qr_code) && $show->show_qr_code == 1)
                                <?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS2D::getBarcodePNG($qrText, 'QRCODE') . '" alt="QRcode"   />'; ?>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
            <!-- <div class="centered" style="margin:30px 0 50px">
            <small>{{ __('db.Invoice Generated By') }} {{ $general_setting->site_title }}.
            {{ __('db.Developed By') }} LionCoders</strong></small>
        </div> -->
        </div>
    </div>

    <script type="text/javascript">
        localStorage.clear();

        function auto_print() {
            window.print();
        }
        //setTimeout(auto_print, 1000);
    </script>

</body>

</html>
