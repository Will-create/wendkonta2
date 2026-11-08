<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $lims_sale_data->customer->name . '_Sale_' . $lims_sale_data->reference_no }}</title>
    <style>
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
            margin: 0 auto;
            padding: 8mm;
            box-sizing: border-box;
        }

        .note-block {
            margin-top: 8px;
            font-size: 11px;
            line-height: 1.35;
            word-wrap: break-word;
            max-width: 190mm;
        }
        
     .header-image {
            width: 100%;    
            height: 80px;
            display: block;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .invoice-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5px;
            gap: 15px;
        }
        
        .client-info {
            font-size: 11px;
            line-height: 1.4;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 5px;
            flex: 1;
            background: #fafafa;
        }
        
        .client-label {
            font-size: 12px;
            font-weight: 600;
            color: #000;
            display: block;
        }
        
        .client-details {
            padding: 2px;
            min-height: 20px;
        }
        
        .facture-box {
            border: 1px solid #000;
            text-align: center;
            min-width: 120px;
            border-radius: 6px;
            background: white;
            align-self: flex-start;
        }
        
        
        .facture-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #000;
        }
        
        .facture-number {
            font-size: 10px;
        }
        
        .table-container {
            border: 1px solid #000;
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
            padding: 8px 4px;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            border: 1px solid #ccc;
            padding: 5px 4px;
            font-size: 10px;
            min-height: 20px;
            background: white;
        }
        
        tr:nth-child(even) td {
            background: #f9f9f9;
        }
        
        .designation-col {
            width: 30%;
        }

        .delivered-col  {
            width: 30%;
        }
        
        .quantity-col, .unit-price-col, .total-col {
            width: 25%;
            text-align: center;
        }
        
        .total-row {
            background: #e9e9e9;
            font-weight: 700;
            border-top: 2px solid #000;
        }
        
        .total-row td {
            padding: 10px 4px;
            font-size: 11px;
            border: 1px solid #000;
        }
        
        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            font-size: 10px;
            gap: 20px;
        }
        
        .signature-section {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 6px;
            flex: 1;
            background: #fafafa;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            display: inline-block;
            margin-bottom: 5px;
            font-size: 9px;
        }

        .hidden-print {
            margin-bottom: 15px;
        }

        .hidden-print table {
            border: none;
        }

        .hidden-print td {
            border: none;
            padding: 5px 10px 5px 0;
        }

        .btn {
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            font-size: 12px;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        /* Optimisation pour l'impression */
        @media print {
            .hidden-print {
                display: none !important;
            }

            @page { size: A4; margin: 8mm; }
            
            body { margin: 0; padding: 0; }
            .facture { padding: 0; max-width: 100%; }
            .header-image { margin-bottom: 10px; }
            .invoice-info { margin-bottom: 10px; }
            .table-container { margin-bottom: 5px; }
            .footer { margin-top: 10px; }
            
            tr:nth-child(even) td {
                background-color: #f9f9f9 !important;
                -webkit-print-color-adjust: exact;
            }
        }
        
        /* Lignes vides compactes */
        .empty-row {
            height: 22px;
        }
        
        .empty-row td {
            padding: 3px 4px;
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
        <img src="/logo/wendkonta.png" alt="ETS. WEND KONTA & FRERES Header" class="header-image">
        
        <div class="invoice-info">
            <div class="client-info">
                
                <div class="client-details">
                    Client: {{ $lims_customer_data->name ?? '________________________________' }} {{ $lims_customer_data->phone_number ? '('.$lims_customer_data->phone_number. ')' : '' }}
                    @if(!empty($lims_customer_data->address) || !empty($lims_customer_data->city))
                        <br>{{ trim(($lims_customer_data->address ?? '').' '.($lims_customer_data->city ?? '')) }}
                    @endif
                </div>
                
            </div>
            
            <div class="facture-box">
                <div class="facture-number">N° {{ $lims_sale_data->reference_no }}</div>
                <div style="font-size: 9px; margin-top: 4px;">
                    {{ date('d/m/Y', strtotime($lims_sale_data->created_at)) }}
                </div>
            </div>
        </div>
        
        @php
            $deliveryLineMap = [];
            $deliveryTracked = \App\Services\DeliveryProgress::saleUsesTrackedLines((int) $lims_sale_data->id);
            foreach (\App\Services\DeliveryProgress::linesForSale((int) $lims_sale_data->id) as $deliveryLine) {
                $deliveryLineMap[$deliveryLine['product_sale_id']] = $deliveryLine;
            }
        @endphp
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="designation-col">DESIGN</th>
                        <th class="quantity-col">Qtité</th>
                        <th class="unit-price-col">Prix U.</th>
                        <th class="total-col">Total</th>
                        <th class="unit-price-col">Qté Livré</th>
                        <th class="quantity-col">Non Livré</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lims_product_sale_data as $key => $product_sale_data)
                        <?php
                        $lims_product_data = \App\Models\Product::find($product_sale_data->product_id);
                        $productName = $lims_product_data->name ?? ('Produit retiré #'.$product_sale_data->product_id);
                        if ($product_sale_data->sale_unit_id) {
                            $unit = \App\Models\Unit::select('unit_code')->find($product_sale_data->sale_unit_id);
                            $unit_code = $unit->unit_code ?? '';
                        } else {
                            $unit_code = '';
                        }

                        if ($product_sale_data->variant_id) {
                            $variant = \App\Models\Variant::select('name')->find($product_sale_data->variant_id);
                            $variant_name = $variant->name ?? '';
                        } else {
                            $variant_name = '';
                        }

                        $topping_names = [];
                        $topping_price_sum = 0;

                        if ($product_sale_data->topping_id) {
                            $decoded_topping_id = is_string($product_sale_data->topping_id) ? json_decode($product_sale_data->topping_id, true) : $product_sale_data->topping_id;
                            if (is_array($decoded_topping_id)) {
                                foreach ($decoded_topping_id as $topping) {
                                    $topping_names[] = $topping['name'];
                                    $topping_price_sum += $topping['price'];
                                }
                            }
                        }

                        $total = ($product_sale_data->net_unit_price + $topping_price_sum) * $product_sale_data->qty;
                        $trackedLine = $deliveryLineMap[$product_sale_data->id] ?? null;
                        if ($deliveryTracked && $trackedLine) {
                            $qtyDelivered = $trackedLine['delivered'];
                            $qtyRemaining = $trackedLine['remaining'];
                        } else {
                            $qtyDelivered = (float) $product_sale_data->dqty;
                            $qtyRemaining = max(0, (float) $product_sale_data->qty - (float) $product_sale_data->return_qty - $qtyDelivered);
                        }
                        ?>
                        <tr>
                            <td>
                                {!! $productName !!}

                                @if (!empty($topping_names))
                                    <br><small>({{ implode(', ', $topping_names) }})</small>
                                @endif

                                @if ($variant_name)
                                    <br><small>{{ $variant_name }}</small>
                                @endif

                                @if ($product_sale_data->imei_number && !str_contains($product_sale_data->imei_number, 'null'))
                                    <br><small>IMEI: {{ $product_sale_data->imei_number }}</small>
                                @endif

                                @if (isset($product_sale_data->warranty_duration))
                                    <br><small><strong>Warranty:</strong> {{ $product_sale_data->warranty_duration }} (Expire: {{ $product_sale_data->warranty_end }})</small>
                                @endif

                                @if (isset($product_sale_data->guarantee_duration))
                                    <br><small><strong>Guarantee:</strong> {{ $product_sale_data->guarantee_duration }} (Expire: {{ $product_sale_data->guarantee_end }})</small>
                                @endif
                            </td>
                            <td style="text-align: center;"> {{ $product_sale_data->qty }}</td>
                            <td style="text-align: center;">{{ number_format($product_sale_data->net_unit_price + $topping_price_sum, $general_setting->decimal, '.', ',') }} FCFA</td>
                            <td style="text-align: center;">{{ number_format($total, $general_setting->decimal, '.', ',') }} FCFA</td>
                             <td style="text-align: center;">{{ \App\Services\DeliveryProgress::formatQty((float) $qtyDelivered) }}</td>
                            <td style="text-align: center;">{{ \App\Services\DeliveryProgress::formatQty((float) $qtyRemaining) }}</td>
                        </tr>
                    @endforeach

                    @php
                        $remaining_rows = max(0, 8 - count($lims_product_sale_data));
                    @endphp

                    @for ($i = 0; $i < $remaining_rows; $i++)
                        <tr class="empty-row">
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right; font-weight: bold;">TOTAL GENERAL</td>
                        <td style="text-align: center;">{{ number_format((float) $lims_sale_data->grand_total, $general_setting->decimal, '.', ',') }} FCFA</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="footer">
            <div class="signature-section">
                <div class="signature-line">
                    Arrêté la présente facture à la somme de:
                    @if(isset($numberInWords))
                        <br>{{ ucfirst(str_replace('-', ' ', $numberInWords)) }} Francs CFA
                    @endif
                </div>
                <br><br>
                <div style="font-weight: 600;">Le Vendeur</div>
            </div>
            <div class="signature-section">
                <br><br>
                <div style="font-weight: 600;">Le Client</div>
            </div>
        </div>
        @if(!empty($lims_sale_data->sale_note))
            <div class="note-block"><strong>Note:</strong> {{ $lims_sale_data->sale_note }}</div>
        @endif
        @if(!empty($lims_sale_data->staff_note))
            <div class="note-block"><strong>Note interne:</strong> {{ $lims_sale_data->staff_note }}</div>
        @endif
    </div>

    <script type="text/javascript">
        function auto_print() {
            window.print();
        }
        // Uncomment the line below to auto-print
        // setTimeout(auto_print, 1000);
    </script>
</body>
</html>