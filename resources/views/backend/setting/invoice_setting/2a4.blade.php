<!DOCTYPE html>
<html lang="fr">
@php
    $show = json_decode($invoice_settings->show_column);
@endphp

<head>
    <!-- <link rel="icon" type="image/png" href="{{ url('logo', $general_setting->site_logo) }}" /> -->
    <title>{{ $lims_sale_data->customer->name . '_Sale_' . $lims_sale_data->reference_no }}</title>
    @php
        $primary_color =
            isset($show->active_primary_color) &&
            $show->active_primary_color == 1 &&
            !empty($invoice_settings->primary_color)
                ? $invoice_settings->primary_color
                : '#014b94';
    @endphp
    <style type="text/css">
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: white;
            font-size: 12px;
        }
        
        .facture {
            width: 100%;
            max-width: 210mm;
            background: white;
            margin: 0;
            padding: 10mm;
            box-sizing: border-box;
        }
        
        .header {
            text-align: center;
            border: 2px solid #000;
            border-radius: 8px;
            padding: 8px 15px;
            margin-bottom: 8px;
            background: white;
        }
        
        .company-name {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: .5px;
            margin-bottom: 4px;
            text-transform: uppercase;
            color: #000;
        }
        
        .company-details {
            font-size: 13px;
            line-height: 1.3;
            font-weight: 500;
            color: #333;
        }
        
        .invoice-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            gap: 15px;
        }
        
        .contact-info {
            font-size: 10px;
            line-height: 1.3;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 8px;
            flex: 1;
            background: #fafafa;
        }
        
        .facture-box {
            border: 2px solid #000;
            padding: 8px 12px;
            text-align: center;
            min-width: 100px;
            border-radius: 6px;
            background: white;
        }
        
        .facture-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #000;
        }
        
        .facture-number {
            font-size: 12px;
            border: 1px solid #333;
            padding: 3px 8px;
            margin-top: 4px;
            border-radius: 4px;
            background: #f8f8f8;
        }
        
        .icons {
            display: flex;
            justify-content: space-around;
            margin: 8px 0;
            font-size: 10px;
            gap: 8px;
        }
        
        .icon-item {
            border: 1px solid #ddd;
            border-radius: 15px;
            padding: 4px 12px;
            text-align: center;
            flex: 1;
            background: white;
            font-size: 9px;
        }
        
        .client-section {
            margin-bottom: 8px;
        }
        
        .client-label {
            font-size: 11px;
            font-weight: 600;
            color: #000;
            border-bottom: 1px solid #333;
            padding: 4px 0;
            display: inline-block;
            margin-bottom: 8px;
        }
        
        .table-container {
            border: 2px solid #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f0f0f0;
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            border: 1px solid #ccc;
            padding: 4px;
            font-size: 10px;
            min-height: 18px;
            background: white;
        }
        
        tr:nth-child(even) td {
            background: #f9f9f9;
        }
        
        .designation-col {
            width: 30%;
        }

        .delivered-col {
            width: 25%;
        }
        
        .quantity-col, .unit-price-col, .total-col {
            width: 15%;
            text-align: center;
        }
        
        .total-row {
            background: #e9e9e9;
            font-weight: 700;
            border-top: 2px solid #000;
        }
        
        .total-row td {
            padding: 8px 4px;
            font-size: 11px;
            border: 1px solid #000;
        }
        
        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 10px;
            gap: 20px;
        }
        
        .signature-section {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 8px;
            flex: 1;
            background: #fafafa;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            display: inline-block;
            margin-bottom: 6px;
            padding-bottom: 2px;
            font-size: 9px;
        }

        .hidden-print {
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            margin-right: 10px;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .totals-section {
            margin-top: 20px;
            border: 2px solid #000;
            border-radius: 8px;
            overflow: hidden;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px;
            border: 1px solid #ccc;
            font-size: 11px;
        }

        .totals-label {
            background: #f0f0f0;
            font-weight: 600;
            width: 70%;
        }

        .totals-value {
            text-align: right;
            font-weight: 700;
        }

        .grand-total-row {
            background: #e9e9e9;
            border-top: 2px solid #000;
        }

        .grand-total-row td {
            font-weight: 700;
            font-size: 12px;
        }
        
        /* Optimisation pour l'impression */
        @media print {
            body { margin: 0; padding: 0; }
            .facture { padding: 5mm; }
            .header { margin-bottom: 5px; padding: 6px 12px; }
            .invoice-info { margin-bottom: 5px; }
            .icons { margin: 5px 0; }
            .client-section { margin-bottom: 5px; }
            .table-container { margin-bottom: 5px; }
            .footer { margin-top: 8px; }
            .hidden-print { display: none !important; }
        }
    </style>
</head>

<body>
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
    </div>

    <div class="facture">
        <div class="header">
            @if (isset($show->show_warehouse_info) && $show->show_warehouse_info == 1)
                <div class="company-name">{{ $general_setting->company_name ?? $lims_biller_data->company_name }}</div>
                <div class="company-details">
                    <div>
                        <span>{{ __('db.Address') }}:</span>&nbsp;&nbsp;<span>{{ $lims_warehouse_data->address }}</span>
                    </div>
                    <div>
                        <span>{{ __('db.Phone') }}:</span>&nbsp;&nbsp;<span>{{ $lims_warehouse_data->phone }}</span>
                    </div>
                    <?php
                    foreach ($sale_custom_fields as $key => $fieldName) {
                        $field_name = str_replace(' ', '_', strtolower($fieldName));
                        echo '<div><span>' . $fieldName . ': ' . $lims_sale_data->$field_name . '</span></div>';
                    }
                    foreach ($customer_custom_fields as $key => $fieldName) {
                        $field_name = str_replace(' ', '_', strtolower($fieldName));
                        echo '<div><span>' . $fieldName . ': ' . $lims_customer_data->$field_name . '</span></div>';
                    }
                    ?>
                </div>
            @else
                <div class="company-name">{{ $general_setting->company_name ?? $lims_biller_data->company_name }}</div>
                <div class="company-details">
                    <em>Commerce Général</em>
                </div>
            @endif
        </div>
        
        <div class="invoice-info">
            <div class="contact-info">
                @if (isset($show->show_warehouse_info) && $show->show_warehouse_info == 1)
                    {{ $lims_warehouse_data->address }}<br>
                    Tél: {{ $lims_warehouse_data->phone }}<br>
                @endif
                
                @if ($invoice_settings->company_logo)
                    <img src="{{ $invoice_settings->company_logo ? url('invoices', $invoice_settings->company_logo) : url('logo', $general_setting->site_logo) }}"
                            height="{{ $invoice_settings->logo_height ?? 'auto' }}" width="{{ $invoice_settings->logo_width ?? 'auto' }}" style="margin:5px 0; max-width: 100px;">
                @endif
            </div>
            
            <div class="facture-box">
                <div class="facture-title">FACTURE</div>
                <div class="facture-number">N° {{ $lims_sale_data->reference_no }}</div>
                <div style="font-size: 10px; margin-top: 8px;">
                    Date: 
                    @if (isset($show->active_date_format) && $show->active_date_format == 1)
                        {{ Carbon\Carbon::parse($lims_sale_data->created_at)->format($invoice_settings->invoice_date_format) }}
                    @else
                        {{ $lims_sale_data->created_at }}
                    @endif
                </div>
                
                @if ($paid_by_info)
                    <div style="font-size: 10px;">
                        {{ __('db.Paid By') }}: {{ $paid_by_info }}
                    </div>
                @endif

                @if (isset($show->show_biller_info) && $show->show_biller_info == 1)
                    <div style="font-size: 10px;">
                        {{ __('db.Served By') }}: {{ $lims_bill_by['name'] }} - ({{ $lims_bill_by['user_name'] }})
                    </div>
                @endif
            </div>
        </div>
        
        <div class="icons">
            <div class="icon-item">🏗️ Construction</div>
            <div class="icon-item">🚛 Transport</div>
            <div class="icon-item">🏢 Commerce</div>
        </div>
        
        <div class="client-section">
            @if (isset($show->show_bill_to_info) && $show->show_bill_to_info == 1)
                <div class="client-label">
                    Client: {{ $lims_customer_data->name }}
                    @if ($lims_sale_data->sale_type == 'online')
                        - {{ $lims_sale_data->shipping_name }}, {{ $lims_sale_data->shipping_address }}, {{ $lims_sale_data->shipping_city }}, {{ $lims_sale_data->shipping_country }}, {{ $lims_sale_data->shipping_zip }}
                    @else
                        - {{ $lims_customer_data->address }}
                    @endif
                    @if (isset($lims_customer_data->phone_number) || isset($lims_sale_data->shipping_phone))
                        - Tél: 
                        @if ($lims_sale_data->sale_type == 'online')
                            {{ $lims_sale_data->shipping_phone }}
                        @else
                            {{ $lims_customer_data->phone_number }}
                        @endif
                    @endif
                </div>
            @else
                <div class="client-label">Client: ________________________________</div>
            @endif
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="designation-col">DESIGNATION</th>
                        <th class="quantity-col">Quantité</th>
                        <th class="unit-price-col">P. Unitaire</th>
                        <th class="total-col">Prix Total</th>
                        <th class="delivered-col">Qté Livrée</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_product_tax = 0;
                    $totalPrice = 0;
                    ?>

                    @foreach ($lims_product_sale_data as $key => $product_sale_data)
                        <?php
                        $lims_product_data = \App\Models\Product::find($product_sale_data->product_id);
                        if ($product_sale_data->sale_unit_id) {
                            $unit = \App\Models\Unit::select('unit_code')->find($product_sale_data->sale_unit_id);
                            $unit_code = $unit->unit_code;
                        } else {
                            $unit_code = '';
                        }

                        if ($product_sale_data->variant_id) {
                            $variant = \App\Models\Variant::select('name')->find($product_sale_data->variant_id);
                            $variant_name = $variant->name;
                        } else {
                            $variant_name = '';
                        }
                        $totalPrice += $product_sale_data->net_unit_price * $product_sale_data->qty;

                        $topping_names = [];
                        $topping_prices = [];
                        $topping_price_sum = 0;

                        if ($product_sale_data->topping_id) {
                            $decoded_topping_id = is_string($product_sale_data->topping_id) ? json_decode($product_sale_data->topping_id, true) : $product_sale_data->topping_id;
                            if (is_array($decoded_topping_id)) {
                                foreach ($decoded_topping_id as $topping) {
                                    $topping_names[] = $topping['name'];
                                    $topping_prices[] = $topping['price'];
                                    $topping_price_sum += $topping['price'];
                                }
                            }
                        }

                        $net_price_with_toppings = $product_sale_data->net_unit_price + $topping_price_sum;
                        $total = ($product_sale_data->net_unit_price + $topping_price_sum) * $product_sale_data->qty;

                        $subtotal = $product_sale_data->total + $topping_price_sum;
                        ?>
                        <tr>
                            <td class="designation-col">
                                {!! $lims_product_data->name !!}

                                @if (!empty($topping_names))
                                    <br><small>({{ implode(', ', $topping_names) }})</small>
                                @endif

                                @foreach ($product_custom_fields as $index => $fieldName)
                                    <?php $field_name = str_replace(' ', '_', strtolower($fieldName)); ?>
                                    @if ($lims_product_data->$field_name)
                                        <br>
                                        <span style="font-weight: bold;">{{ $fieldName }}</span>
                                        {{ ': ' . $lims_product_data->$field_name }}
                                    @endif
                                @endforeach
                                
                                @if ($product_sale_data->imei_number && !str_contains($product_sale_data->imei_number, 'null'))
                                    <br><small>IMEI or Serial: {{ $product_sale_data->imei_number }}</small>
                                @endif
                                
                                @if (isset($product_sale_data->warranty_duration))
                                    <br>
                                    <span style="font-weight: bold;">Warranty</span>{{ ': ' . $product_sale_data->warranty_duration }}
                                    <br>
                                    <span style="font-weight: bold;">Will Expire</span>{{ ': ' . $product_sale_data->warranty_end }}
                                @endif
                                
                                @if (isset($product_sale_data->guarantee_duration))
                                    <br>
                                    <span style="font-weight: bold;">Guarantee</span>{{ ': ' . $product_sale_data->guarantee_duration }}
                                    <br>
                                    <span style="font-weight: bold;">Will Expire</span>{{ ': ' . $product_sale_data->guarantee_end }}
                                @endif
                            </td>
                            <td class="quantity-col">
                                {{ $product_sale_data->qty . ' ' . $unit_code . ' ' . $variant_name }}
                            </td>
                            <td class="unit-price-col">
                                {{ number_format($product_sale_data->net_unit_price, $general_setting->decimal, '.', ',') }}
                                @if (!empty($topping_prices))
                                    <br><small>+
                                        {{ implode(' + ', array_map(fn($price) => number_format($price, $general_setting->decimal, '.', ','), $topping_prices)) }}</small>
                                @endif
                            </td>
                            <td class="total-col">
                                {{ number_format($total, $general_setting->decimal, '.', ',') }}
                            </td>
                            <td class="delivered-col">&nbsp;</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="4" style="text-align: center; font-weight: bold;">TOTAL GENERAL</td>
                        <td style="text-align: center;">&nbsp;</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Totals Section -->
        <div class="totals-section">
            <table class="totals-table">
                {{-- <tr>
                    <td class="totals-label">{{ __('db.Total Before Tax') }}</td>
                    <td class="totals-value">
                        {{ number_format((float) ($lims_sale_data->total_price - ($lims_sale_data->total_tax + $lims_sale_data->order_tax)), $general_setting->decimal, '.', ',') }}
                    </td>
                </tr> --}}
                
                {{-- Tax rows commented out
                @if ($general_setting->invoice_format == 'gst' && $general_setting->state == 1)
                    <tr>
                        <td class="totals-label">IGST</td>
                        <td class="totals-value">
                            {{ number_format((float) ($lims_sale_data->total_tax + $lims_sale_data->order_tax), $general_setting->decimal, '.', ',') }}
                        </td>
                    </tr>
                @elseif($general_setting->invoice_format == 'gst' && $general_setting->state == 2)
                    <tr>
                        <td class="totals-label">SGST</td>
                        <td class="totals-value">
                            {{ number_format(($lims_sale_data->total_tax + $lims_sale_data->order_tax) / 2, $general_setting->decimal, '.', ',') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="totals-label">CGST</td>
                        <td class="totals-value">
                            {{ number_format(($lims_sale_data->total_tax + $lims_sale_data->order_tax) / 2, $general_setting->decimal, '.', ',') }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td class="totals-label">{{ __('db.Tax') }}</td>
                        <td class="totals-value">
                            {{ number_format((float) ($lims_sale_data->total_tax + $lims_sale_data->order_tax), $general_setting->decimal, '.', ',') }}
                        </td>
                    </tr>
                @endif
                --}}
                
                <tr>
                    <td class="totals-label">{{ __('db.Discount') }}</td>
                    <td class="totals-value">
                        {{ number_format((float) ($lims_sale_data->total_discount + $lims_sale_data->order_discount), $general_setting->decimal, '.', ',') }}
                    </td>
                </tr>
                
                <tr class="grand-total-row">
                    <td class="totals-label">{{ __('db.grand total') }}</td>
                    <td class="totals-value">
                        {{ number_format((float) $lims_sale_data->grand_total, $general_setting->decimal, '.', ',') }}
                    </td>
                </tr>
                
                <tr>
                    <td class="totals-label">{{ __('db.Paid') }}</td>
                    <td class="totals-value">
                        {{ number_format((float) $lims_sale_data->paid_amount, $general_setting->decimal, '.', ',') }}
                    </td>
                </tr>
                
                <tr>
                    @if ($change_amount > 0)
                        <td class="totals-label">{{ __('db.Change') }}</td>
                        <td class="totals-value">
                            {{ number_format((float) ($change_amount), $general_setting->decimal, '.', ',') }}
                        </td>
                    @else
                        <td class="totals-label">{{ __('db.Due') }}</td>
                        <td class="totals-value">
                            {{ number_format((float) ($lims_sale_data->grand_total - $lims_sale_data->paid_amount), $general_setting->decimal, '.', ',') }}
                        </td>
                    @endif
                </tr>

                @if ($totalDue && isset($show->hide_total_due))
                    @if (!$show->hide_total_due)
                    <tr>
                        <td class="totals-label">{{ __('db.Total Due') }}</td>
                        <td class="totals-value">
                            {{ number_format($totalDue, $general_setting->decimal, '.', ',') }}
                        </td>
                    </tr>
                    @endif
                @endif

                @if (isset($show->show_in_words) && $show->show_in_words == 1)
                <tr>
                    <td colspan="2" style="padding: 10px; text-align: center; font-size: 10px;">
                        {{ __('db.In Words') }}: 
                        @if ($general_setting->currency_position == 'prefix')
                            {{ $currency_code }} <span style="text-transform:capitalize;">{{ str_replace('-', ' ', $numberInWords) }}</span> only
                        @else
                            <span style="text-transform:capitalize;">{{ str_replace('-', ' ', $numberInWords) }}</span> {{ $currency_code }} only
                        @endif
                    </td>
                </tr>
                @endif
            </table>
        </div>

        @if (isset($show->show_payment_note) && $show->show_payment_note == 1)
            <div style="margin-top: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa;">
                <strong>{{ __('db.Note') }}:</strong><br>{{ $lims_sale_data->sale_note }}
            </div>
        @endif
        
        <div class="footer">
            <div class="signature-section">
                <div class="signature-line">
                    Arrêté la présente facture à la somme de:
                </div>
                <br><br>
                <div style="font-weight: 600;">Le Vendeur</div>
            </div>
            <div class="signature-section">
                <br><br><br>
                <div style="font-weight: 600;">Le Client</div>
            </div>
        </div>

        <!-- Barcode and QR Code section -->
        <div style="text-align: center; margin-top: 20px;">
            @if (isset($show->show_barcode) && $show->show_barcode == 1)
                <?php echo '<img style="max-width:100%" src="data:image/png;base64,' . DNS1D::getBarcodePNG($lims_sale_data->reference_no, 'C128') . '" alt="barcode"   />'; ?>
                <br><br>
            @endif
            
            @if (isset($show->show_qr_code) && $show->show_qr_code == 1)
                <?php echo '<img style="width:5%" src="data:image/png;base64,' . DNS2D::getBarcodePNG($qrText, 'QRCODE') . '" alt="barcode"   />'; ?>
                <br>
            @endif
            
            @if (isset($show->show_footer_text) && $show->show_footer_text == 1)
                <br><strong>{{ $invoice_settings->footer_text ?? __('db.Thank you for shopping with us Please come again') }}</strong>
            @endif
        </div>
    </div>

    <script type="text/javascript">
        // localStorage.clear(); // Commented out as requested to avoid browser storage usage

        function auto_print() {
            window.print();
        }
        //setTimeout(auto_print, 1000);
    </script>
</body>

</html>