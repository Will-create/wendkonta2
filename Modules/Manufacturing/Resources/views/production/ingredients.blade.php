        <?php
        $product_list = explode(',', $lims_product_data->product_list);
        $wastage_percent = explode(',', $lims_product_data->wastage_percent);
        $qty_list = explode(',', $lims_product_data->qty_list);
        $variant_list = $lims_product_data->variant_list ? explode(',', $lims_product_data->variant_list) : null;
        $price_list = explode(',', $lims_product_data->price_list);
        ?>
        @foreach ($product_list as $key => $id)
            <tr>
                <?php
                $product = App\Models\Product::find($id);
                $stock = App\Models\Product_Warehouse::where([['product_id',$product->id],['warehouse_id',$warehouse_id]])->latest()->first();
                $combo_unit = App\Models\Unit::query()->where('id', $product->unit_id)->orWhere('base_unit', $product->unit_id)->get()->unique('id');
                $unit = App\Models\Unit::query()->where('id', $product->unit_id)->first();
                if ($lims_product_data->variant_list && isset($variant_list[$key]) && $variant_list[$key] != "") {
                    $product_variant_data = App\Models\ProductVariant::select('item_code')->FindExactProduct($id, $variant_list[$key])->first();
                    $product->code = $product_variant_data->item_code;
                } else {
                    $variant_list[$key] = '';
                }
                ?>
                <td>{{ $product->name }} [{{ $product->code }}]</td>
                <td>
                    <div class="input-group">
                        <input type="number" class="form-control wastage_percent" name="wastage_percent[]"
                            value="{{ @$wastage_percent[$key] ?? 0 }}" min="0" step="any" />
                        <div class="input-group-append">
                            <span class="input-group-text">%</span>
                        </div>
                </td>
                <td>
                    <div class="input-group" style="max-width: unset">
                        <input type="number" class="form-control qty" name="product_qty[]" data-qty="{{ $qty_list[$key] }}"
                            value="{{ $qty_list[$key] ?? 1 }}" step="any" placeholder="Qty" aria-label="Quantity" data-stock="{{ $stock->qty ?? 0 }}">

                        <div class="input-group-append">
                            <select name="production_unit_ids[]" style="width: 112px;"
                                class="btn btn-outline-secondary form-control production_unit_ids"
                                onchange="calculate_price()">
                                @foreach ($combo_unit as $row)
                                    <option value="{{ $row->id }}"
                                        data-operation_value="{{ $row->operation_value }}"
                                        data-unit_name="{{ $row->unit_name }}"
                                        data-operator="{{ $row->operator }}"
                                        @if ($lims_product_data->unit_id == $row->id) 'selected' @endif>
                                        {{ $row->unit_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="stock_list[]" value="{{ $stock->qty ?? 0 }}">
                            <span class="text-danger qty-error"></span>
                    </div>
                </td>


                <td><input type="text" class="form-control unit_name" disabled
                        value="{{  $qty_list[$key] ?? 1  }} ({{ $unit->unit_name }})" step="any" /></td>
                <td><input type="number" class="form-control unit_price" name="unit_price[]"
                        value="{{ $price_list[$key] }}" step="any" /></td>
                <td><input type="number" class="form-control subtotal" name="subtotal[]" value="0.00"
                        step="any" /></td>
                <td><button type="button" class="ibtnDel btn btn-danger btn-sm">X</button></td>
                <input type="hidden" class="product-id" name="product_list[]" value="{{ $id }}" />
                <input type="hidden" class="variant-id" name="variant_id[]" value="{{ $variant_list[$key] }}" />
            </tr>
        @endforeach
