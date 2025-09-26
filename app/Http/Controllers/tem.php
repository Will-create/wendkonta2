public function store(StoreSaleRequest $request)
{  
       $data = $request->all();
    // DB::beginTransaction();
    /*try {*/
        if(isset($request->reference_no)) {
            $this->validate($request, [
                'reference_no' => [
                    'max:191', 'required', 'unique:sales'
                ],
            ]);
        }

        $data['user_id'] = Auth::id();

	        $cash_register_data = CashRegister::where([
	            ['user_id', $data['user_id']],
	            ['warehouse_id', $data['warehouse_id']],
	            ['status', true]
	        ])->first();

	        if($cash_register_data)
	            $data['cash_register_id'] = $cash_register_data->id;

	        if(cache()->has('general_setting'))
	        {
	            $general_setting = cache()->get('general_setting');
	        }else {
	            $general_setting = GeneralSetting::latest()->first();
	            cache()->put('general_setting', $general_setting, 60 * 60 * 24);
	        }

	        if (isset($data['created_at'])) {
	            $date = DateTime::createFromFormat('Y-m-d', $data['created_at']);
	            if ($date) {
	                $data['created_at'] = $date->format('Y-m-d H:i:s');
	            } else {
	                // fallback or error
	                $data['created_at'] = date('Y-m-d H:i:s');
	            }
	        } else {
	            $data['created_at'] = date('Y-m-d H:i:s');
	        }

	        //set the paid_amount value to $new_data variable
	        $new_data['paid_amount'] = $data['paid_amount'];

	        if (is_array($data['paid_amount'])) {
	            $data['paid_amount'] = array_sum($data['paid_amount']);
	        }

	        if(isset($data['pos'])) {
	            if(!isset($data['reference_no']))

	            // invoice implement new (27-04-25)
	            $data['reference_no'] = $this->generateInvoiceName('posr-');

	            // foreach($new_data['paid_amount'] as $paid_amount)
	            // {
	            //     $balance = $data['grand_total'] - $paid_amount;
	            // }
	            $balance = $data['grand_total'] - $data['paid_amount'];

	            if (is_array($data['paid_amount'])) {
	                $data['paid_amount'] = array_sum($data['paid_amount']);
	            }
	            if($balance > 0 || $balance < 0)
	                $data['payment_status'] = 2;
	            else
	                $data['payment_status'] = 4;

	            if($data['draft']) {
	                $lims_sale_data = Sale::find($data['sale_id']);
	                $lims_product_sale_data = Product_Sale::where('sale_id', $data['sale_id'])->get();
	                foreach ($lims_product_sale_data as $product_sale_data) {
	                    $product_sale_data->delete();
	                }
	                $lims_sale_data->delete();
	            }
	        }
	        else {
	            if(!isset($data['reference_no']))
	                $data['reference_no'] =$this->generateInvoiceName('sr-');
	                // $data['reference_no'] = 'sr-' . date("Ymd") . '-'. date("his");
	        }

	        $document = $request->document;
	        if ($document) {
	            $v = Validator::make(
	                [
	                    'extension' => strtolower($request->document->getClientOriginalExtension()),
	                ],
	                [
	                    'extension' => 'in:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
	                ]
	            );
	            if ($v->fails())
	                return redirect()->back()->withErrors($v->errors());

	            $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
	            $documentName = date("Ymdhis");
	            if(!config('database.connections.saleprosaas_landlord')) {
	                $documentName = $documentName . '.' . $ext;
	                $document->move(public_path('documents/sale'), $documentName);
	            }
	            else {
	                $documentName = $this->getTenantId() . '_' . $documentName . '.' . $ext;
	                $document->move(public_path('documents/sale'), $documentName);
	            }
	            $data['document'] = $documentName;
	        }
	        if($data['coupon_active'] && !$data['draft']) {
	            $lims_coupon_data = Coupon::find($data['coupon_id']);
	            $lims_coupon_data->used += 1;
	            $lims_coupon_data->save();
	        }
	        if(isset($data['table_id'])) {
	            $latest_sale = Sale::whereNotNull('table_id')->whereDate('created_at', date('Y-m-d'))->where('warehouse_id', $data['warehouse_id'])->select('queue')->orderBy('id', 'desc')->first();
	            if($latest_sale)
	                $data['queue'] = $latest_sale->queue + 1;
	            else
	                $data['queue'] = 1;
	        }

        //inserting data to sales table
        $lims_sale_data = Sale::create($data);

        // add the $new_data variable value to $data['paid_amount'] variable
        $data['paid_amount'] = $new_data['paid_amount'];

        //inserting data for custom fields
        $custom_field_data = [];
        $custom_fields = CustomField::where('belongs_to', 'sale')->select('name', 'type')->get();
        foreach ($custom_fields as $type => $custom_field) {
            $field_name = str_replace(' ', '_', strtolower($custom_field->name));
            if(isset($data[$field_name])) {
                if($custom_field->type == 'checkbox' || $custom_field->type == 'multi_select')
                    $custom_field_data[$field_name] = implode(",", $data[$field_name]);
                else
                    $custom_field_data[$field_name] = $data[$field_name];
            }
        }
        if(count($custom_field_data))
            DB::table('sales')->where('id', $lims_sale_data->id)->update($custom_field_data);
        $lims_customer_data = Customer::find($data['customer_id']);
        $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
        //checking if customer gets some points or not
        if($lims_reward_point_setting_data && $lims_reward_point_setting_data->is_active &&  $data['grand_total'] >= $lims_reward_point_setting_data->minimum_amount) {
            if ($lims_customer_data->type == CustomerTypeEnum::REGULAR->value) {
                $isDraft = $data['draft'] == '0';
                $isNotPaidBy7 = !in_array('7', $data['paid_by_id']);
                if ($isDraft && $isNotPaidBy7) {
                    $point = (int)($data['grand_total'] / $lims_reward_point_setting_data->per_point_amount);
                    $lims_customer_data->points += $point;
                    $lims_customer_data->save();
                    RewardPoint::create([
                        'points' => $point,
                        'customer_id' => $lims_customer_data->id,
                        'sale_id' => $lims_sale_data->id,
                    ]);
                }
            }
        }

        //collecting mail data
        $mail_data['email'] = $lims_customer_data->email;
        $mail_data['reference_no'] = $lims_sale_data->reference_no;
        $mail_data['sale_status'] = $lims_sale_data->sale_status;
        $mail_data['payment_status'] = $lims_sale_data->payment_status;
        $mail_data['total_qty'] = $lims_sale_data->total_qty;
        $mail_data['total_price'] = $lims_sale_data->total_price;
        $mail_data['order_tax'] = $lims_sale_data->order_tax;
        $mail_data['order_tax_rate'] = $lims_sale_data->order_tax_rate;
        $mail_data['order_discount'] = $lims_sale_data->order_discount;
        $mail_data['shipping_cost'] = $lims_sale_data->shipping_cost;
        $mail_data['grand_total'] = $lims_sale_data->grand_total;
        $mail_data['paid_amount'] = $lims_sale_data->paid_amount;

        $product_id = $data['product_id'];
        $product_batch_id = $data['product_batch_id'];
        $imei_number = $data['imei_number'];
        $product_code = $data['product_code'];
        $qty = $data['qty'];
        $dqty = isset($data['dqty']) ? $data['dqty'] : [];
        $dstatus = isset($data['dstatus']) ? $data['dstatus'] : [];
        $sale_unit = $data['sale_unit'];
        $net_unit_price = $data['net_unit_price'];
        $discount = $data['discount'];
        $tax_rate = $data['tax_rate'];
        $tax = $data['tax'];
        $total = $data['subtotal'];
        $product_sale = [];

        // Arrays to store adjustment data
        $adjustment_products = [];
        $has_adjustments = false;

        foreach ($product_id as $i => $id) {
            // Validate dqty is within acceptable range
            $current_dqty = isset($dqty[$i]) ? (int)$dqty[$i] : 0;
            $current_qty = (int)$qty[$i];
            
            // Ensure dqty is between 0 and qty
            if ($current_dqty < 0) {
                $current_dqty = 0;
            }
            if ($current_dqty > $current_qty) {
                $current_dqty = $current_qty;
            }
            
            $lims_product_data = Product::where('id', $id)->first();
            $product_sale['variant_id'] = null;
            $product_sale['product_batch_id'] = null;


           try {
            DB::beginTransaction();
                if($lims_product_data->type == 'combo' && $data['sale_status'] == 1){
                    $total_request_combo_qty = $qty[$i];
                    $product_list = explode(",", $lims_product_data->product_list);
                    $variant_list = $lims_product_data->variant_list
                        ? explode(",", $lims_product_data->variant_list)
                        : [];
                    $qty_list = explode(",", $lims_product_data->qty_list);
                    $price_list = explode(",", $lims_product_data->price_list);

                    foreach ($product_list as $key => $child_id) {
                        $child_data = Product::find($child_id);

                        if(!$child_data) {
                            continue;
                        }

                        if($sale_unit[$i] != 'n/a'){
                            $lims_sale_unit_data  = Unit::where('unit_name', $sale_unit[$i])->first();
                            if($lims_sale_unit_data->operator == '*')
                                $qty[$i] = $qty[$i] * $lims_sale_unit_data->operation_value;
                            elseif($lims_sale_unit_data->operator == '/')
                                $qty[$i] = $qty[$i] / $lims_sale_unit_data->operation_value;
                        }

                        if(count($variant_list) && isset($variant_list[$key]) && $variant_list[$key]) {
                            $child_product_variant_data = ProductVariant::where([
                                ['product_id', $child_id],
                                ['variant_id', $variant_list[$key]]
                            ])->first();

                            $child_warehouse_data = Product_Warehouse::where([
                                ['product_id', $child_id],
                                ['variant_id', $variant_list[$key]],
                                ['warehouse_id', $data['warehouse_id']],
                            ])->first();

                            if($child_product_variant_data) {
                                // Use delivered quantity (dqty) for stock deduction, not ordered quantity
                                $delivered_qty = $current_dqty * $qty_list[$key];
                                $child_product_variant_data->qty -= $delivered_qty;
                                $child_product_variant_data->save();
                            }
                        } else {
                            $child_warehouse_data = Product_Warehouse::where([
                                ['product_id', $child_id],
                                ['warehouse_id', $data['warehouse_id']],
                            ])->first();
                        }

                        // Use delivered quantity (dqty) for stock deduction, not ordered quantity
                        $delivered_qty = $current_dqty * $qty_list[$key];
                        $child_data->qty -= $delivered_qty;
                        $child_data->save();

                        if($child_warehouse_data) {
                            $child_warehouse_data->qty -= $delivered_qty;
                            $child_warehouse_data->save();
                        }
                    }
                }

                if($sale_unit[$i] != 'n/a' && $lims_product_data->type != 'combo') {
                $lims_sale_unit_data  = Unit::where('unit_name', $sale_unit[$i])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
                if($lims_product_data->is_variant) {
                    $lims_product_variant_data = ProductVariant::select('id', 'variant_id', 'qty')->FindExactProductWithCode($id, $product_code[$i])->first();
                    $product_sale['variant_id'] = $lims_product_variant_data->variant_id;
                }
                if($lims_product_data->is_batch && $product_batch_id[$i]) {
                    $product_sale['product_batch_id'] = $product_batch_id[$i];
                }

                if($data['sale_status'] == 1) {
                    // Calculate delivered quantity based on unit conversion
                    $delivered_quantity = $current_dqty;
                    if($lims_sale_unit_data->operator == '*')
                        $delivered_quantity = $current_dqty * $lims_sale_unit_data->operation_value;
                    elseif($lims_sale_unit_data->operator == '/')
                        $delivered_quantity = $current_dqty / $lims_sale_unit_data->operation_value;
                    
                    //deduct delivered quantity (not ordered quantity)
                    $lims_product_data->qty = $lims_product_data->qty - $delivered_quantity;
                    $lims_product_data->save();
                    //deduct product variant quantity if exist
                    if($lims_product_data->is_variant) {
                        $lims_product_variant_data->qty -= $delivered_quantity;
                        $lims_product_variant_data->save();
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($id, $lims_product_variant_data->variant_id, $data['warehouse_id'])->first();
                    }
                    elseif($product_batch_id[$i]) {
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_batch_id', $product_batch_id[$i] ],
                            ['warehouse_id', $data['warehouse_id'] ]
                        ])->first();
                        $lims_product_batch_data = ProductBatch::find($product_batch_id[$i]);
                        //deduct delivered quantity from batch
                        $lims_product_batch_data->qty -= $delivered_quantity;
                        $lims_product_batch_data->save();
                    }
                    else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($id, $data['warehouse_id'])->first();
                    }
                    //deduct delivered quantity from warehouse
                    $lims_product_warehouse_data->qty -= $delivered_quantity;
                    $lims_product_warehouse_data->save();
                }
            }
            else
                $sale_unit_id = 0;
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Product Stock Update Error: '.$e->getMessage());
                return redirect()->back()->with('error', 'Something went wrong while updating stock.');
            }

            if($product_sale['variant_id']) {
                $variant_data = Variant::select('name')->find($product_sale['variant_id']);
                $mail_data['products'][$i] = $lims_product_data->name . ' ['. $variant_data->name .']';
            }
            else
                $mail_data['products'][$i] = $lims_product_data->name;
            //deduct imei number if available
            if($imei_number[$i] && !str_contains($imei_number[$i], "null") && $data['sale_status'] == 1) {
                $imei_numbers = explode(",", $imei_number[$i]);
                $all_imei_numbers = explode(",", $lims_product_warehouse_data->imei_number);
                foreach ($imei_numbers as $number) {
                    if (($j = array_search($number, $all_imei_numbers)) !== false) {
                        unset($all_imei_numbers[$j]);
                    }
                }

                $lims_product_warehouse_data->imei_number = implode(",", $all_imei_numbers);
                $lims_product_warehouse_data->save();
            }
            if($lims_product_data->type == 'digital')
                $mail_data['file'][$i] = url('/product/files').'/'.$lims_product_data->file;
            else
                $mail_data['file'][$i] = '';
            if($sale_unit_id)
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code;
            else
                $mail_data['unit'][$i] = '';

            $product_sale['sale_id'] = $lims_sale_data->id;
            $product_sale['product_id'] = $id;
            if($imei_number[$i] && !str_contains($imei_number[$i], "null")) {
                $product_sale['imei_number'] = $imei_number[$i];
            } else {
                $product_sale['imei_number'] = null;
            }
            $product_sale['qty'] = $mail_data['qty'][$i] = (int) $qty[$i];
            
            // Properly set dqty and dstatus with validated values
            $product_sale['dqty'] = $current_dqty;
            $product_sale['dstatus'] = isset($dstatus[$i]) && !empty($dstatus[$i]) ? $dstatus[$i] : $current_dqty . ' / ' . $current_qty;
            
            $product_sale['sale_unit_id'] = $sale_unit_id;
            $product_sale['net_unit_price'] = $net_unit_price[$i];
            $product_sale['discount'] = $discount[$i];
            $product_sale['tax_rate'] = $tax_rate[$i];
            $product_sale['tax'] = $tax[$i];
            $product_sale['total'] = $mail_data['total'][$i] = $total[$i];

            if(cache()->has('general_setting'))
            {
                $general_setting = cache()->get('general_setting');
            }else {
                $general_setting = GeneralSetting::select('modules')->first();
                cache()->put('general_setting', $general_setting, 60 * 60 * 24);
            }

            if (in_array('restaurant', explode(',', $general_setting->modules))) {
                $product_sale['topping_id'] = null; // Reset topping ID for each product
                if (!empty($data['topping_product'][$i])) {
                    $product_sale['topping_id'] = $data['topping_product'][$i];
                }
            }

            Product_Sale::create($product_sale);

            // Check if we need to create adjustment for undelivered quantity
            // Only create adjustment if there's a difference and sale is completed
            $qty_difference = $current_qty - $current_dqty;
            if ($qty_difference > 0 && $data['sale_status'] == 1) {
                $adjustment_products[] = [
                    'product_id' => $id,
                    'product_code' => $product_code[$i],
                    'variant_id' => $product_sale['variant_id'],
                    'product_batch_id' => $product_sale['product_batch_id'],
                    'qty_difference' => $qty_difference,
                    'unit_cost' => $net_unit_price[$i],
                    'sale_unit_id' => $sale_unit_id
                ];
                $has_adjustments = true;
            }
        }

        // Create adjustment record for undelivered quantities
        if ($has_adjustments && $data['sale_status'] == 1) {
            try {
                DB::beginTransaction();
                
                // Create adjustment record
                $adjustment_data = [
                    'reference_no' => 'adr-' . date("Ymd") . '-' . date("his"),
                    'warehouse_id' => $data['warehouse_id'],
                    'total_qty' => array_sum(array_column($adjustment_products, 'qty_difference')),
                    'item' => count($adjustment_products),
                    'created_at' => date('Y-m-d H:i:s'),
                    'user_id' => Auth::id(),
                    'note' => 'Stock adjustment for undelivered items - Sale Ref: ' . $lims_sale_data->reference_no
                ];
                
                $lims_adjustment_data = Adjustment::create($adjustment_data);

                // Create product adjustments - increase stock back for undelivered items
                foreach ($adjustment_products as $adj_product) {
                    $lims_product_data = Product::find($adj_product['product_id']);
                    
                    // Calculate actual quantity adjustment based on sale unit
                    $adjustment_qty = $adj_product['qty_difference'];
                    if ($adj_product['sale_unit_id'] > 0) {
                        $lims_sale_unit_data = Unit::find($adj_product['sale_unit_id']);
                        if($lims_sale_unit_data->operator == '*')
                            $adjustment_qty = $adj_product['qty_difference'] * $lims_sale_unit_data->operation_value;
                        elseif($lims_sale_unit_data->operator == '/')
                            $adjustment_qty = $adj_product['qty_difference'] / $lims_sale_unit_data->operation_value;
                    }
                    
                    // Handle variant products
                    if ($lims_product_data->is_variant && $adj_product['variant_id']) {
                        $lims_product_variant_data = ProductVariant::where([
                            ['product_id', $adj_product['product_id']],
                            ['variant_id', $adj_product['variant_id']]
                        ])->first();
                        
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_id', $adj_product['product_id']],
                            ['variant_id', $adj_product['variant_id']],
                            ['warehouse_id', $data['warehouse_id']],
                        ])->first();

                        // Add back undelivered quantity to variant
                        if ($lims_product_variant_data) {
                            $lims_product_variant_data->qty += $adjustment_qty;
                            $lims_product_variant_data->save();
                        }
                    } 
                    elseif ($adj_product['product_batch_id']) {
                        // Handle batch products
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_batch_id', $adj_product['product_batch_id']],
                            ['warehouse_id', $data['warehouse_id']],
                        ])->first();
                        
                        $lims_product_batch_data = ProductBatch::find($adj_product['product_batch_id']);
                        if ($lims_product_batch_data) {
                            $lims_product_batch_data->qty += $adjustment_qty;
                            $lims_product_batch_data->save();
                        }
                    } 
                    else {
                        // Handle regular products
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_id', $adj_product['product_id']],
                            ['warehouse_id', $data['warehouse_id']],
                        ])->first();
                    }

                    // Add back undelivered quantity to main product
                    $lims_product_data->qty += $adjustment_qty;
                    $lims_product_data->save();
                    
                    // Add back undelivered quantity to warehouse
                    if ($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->qty += $adjustment_qty;
                        $lims_product_warehouse_data->save();
                    }

                    // Create product adjustment record (assuming you have a ProductAdjustment model)
                    // If you don't have this model, you'll need to create it or modify the table structure
                    $product_adjustment = [
                        'product_id' => $adj_product['product_id'],
                        'variant_id' => $adj_product['variant_id'],
                        'sale_id' => $lims_sale_data->id,
                        'product_batch_id' => $adj_product['product_batch_id'],
                        'adjustment_id' => $lims_adjustment_data->id,
                        'qty' => $adj_product['qty_difference'],
                        'unit_cost' => $adj_product['unit_cost'],
                        'action' => '+' // Always add back undelivered quantity
                    ];
                    
                    // Use the correct model/table for product adjustments
                    // You may need to create a ProductAdjustment model if it doesn't exist
                    try {
                        ProductAdjustment::create($product_adjustment);
                    } catch (\Exception $e) {
                        // If ProductAdjustment doesn't exist, log the adjustment data
                        \Log::info('Product Adjustment Data (model not found): ', $product_adjustment);
                    }
                }
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Adjustment Creation Error: ' . $e->getMessage());
                // Continue with sale process even if adjustment fails
            }
        }

        
        if($data['sale_status'] == 3)
            $message = 'Sale successfully added to draft';
        else
            $message = ' Sale created successfully';
        $mail_setting = MailSetting::latest()->first();
        if($mail_data['email'] && $data['sale_status'] == 1 && $mail_setting) {
            $this->setMailInfo($mail_setting);
            try {
                Mail::to($mail_data['email'])->send(new SaleDetails($mail_data));
                /*$log_data['message'] = Auth::user()->name . ' has created a sale. Reference No: ' .$lims_sale_data->reference_no;
                $admin_email = 'ashfaqdev.php@gmail.com';
                Mail::to($admin_email)->send(new LogMessage($log_data));*/
            }
            catch(\Exception $e){
                $message = ' Sale created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }

        if($data['payment_status'] == 3 || $data['payment_status'] == 4 || ($data['payment_status'] == 2 && $data['pos'] && $data['paid_amount'] > 0)) {
            foreach($data['paid_by_id'] as $key=>$value)
            {
                if($data['paid_amount'][$key] > 0) {
                    $lims_payment_data = new Payment();
                    $lims_payment_data->user_id = Auth::id();
                    $paying_method = '';

                    if($data['paid_by_id'][$key] == 1)
                        $paying_method = 'Cash';
                    elseif ($data['paid_by_id'][$key] == 2) {
                        $paying_method = 'Gift Card';
                    }
                    elseif ($data['paid_by_id'][$key] == 3)
                        $paying_method = 'Credit Card';
                    elseif ($data['paid_by_id'][$key] == 4)
                        $paying_method = 'Cheque';
                    elseif ($data['paid_by_id'][$key] == 5)
                        $paying_method = 'Paypal';
                    elseif($data['paid_by_id'][$key] == 6)
                        $paying_method = 'Deposit';
                    elseif($data['paid_by_id'][$key] == 7) {
                        $paying_method = 'Points';
                        $lims_payment_data->used_points = $data['used_points'];
                        $lims_customer_data->points -= $data['used_points'];
                        $lims_customer_data->save();
                    }
                    elseif($data['paid_by_id'][$key] == 8) {
                        $paying_method = 'Pesapal';
                    }
                    else {

                        $paying_method = ucfirst($data['paid_by_id'][$key]); // For string values like 'Pesapal', 'Stripe', etc.
                    }

                    if($cash_register_data)
                        $lims_payment_data->cash_register_id = $cash_register_data->id;
                    $lims_account_data = Account::where('is_default', true)->first();
                    if(isset($data['account_id']))
                        $lims_payment_data->account_id = $data['account_id'];
                    else  
                        $lims_payment_data->account_id = $lims_account_data->id;
                    $lims_payment_data->sale_id = $lims_sale_data->id;
                    $data['payment_reference'] = 'spr-'.date("Ymd").'-'.date("his");
                    $lims_payment_data->payment_reference = $data['payment_reference'];
                    $lims_payment_data->amount = $data['paid_amount'][$key];
                    $lims_payment_data->change = $data['paying_amount'][$key] - $data['paid_amount'][$key];
                    $lims_payment_data->paying_method = $paying_method;
                    $lims_payment_data->payment_note = $data['payment_note'];
                    if(isset($data['payment_receiver'])){
                        $lims_payment_data->payment_receiver = $data['payment_receiver'];
                    }
                    $lims_payment_data->save();

                    if(isset($data['cash']) && $data['cash'] > 0 &&  isset($data['bank']) && $data['bank'])

                    $lims_payment_data = Payment::latest()->first();
                    $data['payment_id'] = $lims_payment_data->id;
                    $lims_pos_setting_data = PosSetting::latest()->first();
                    // Check Payment Method is Card
                    if($paying_method == 'Credit Card'){
                        $cardDetails = [];
                        $cardDetails['card_number'] = $data['card_number'];
                        $cardDetails['card_holder_name'] = $data['card_holder_name'];
                        $cardDetails['card_type'] = $data['card_type'];
                        $data['charge_id'] = '12345';
                        $data['data'] = json_encode($cardDetails);

                        PaymentWithCreditCard::create($data);
                    }
                    else if ($paying_method == 'Gift Card') {
                        $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
                        $lims_gift_card_data->expense += $data['paid_amount'][$key];
                        $lims_gift_card_data->save();
                        PaymentWithGiftCard::create($data);
                    }
                    else if ($paying_method == 'Cheque') {
                        PaymentWithCheque::create($data);
                    }
                    else if($paying_method == 'Deposit'){
                        $lims_customer_data->expense += $data['paid_amount'][$key];
                        $lims_customer_data->save();
                    }
                    else if($paying_method == 'Points'){
                        if(!isset($data['draft'])){
                            $lims_customer_data->points -= $data['used_points'];
                            $lims_customer_data->save();
                        }
                    }
                    else if($paying_method == 'Pesapal'){
                        $redirectUrl = $this->submitOrderRequest($lims_customer_data,$data['paid_amount'][$key]); // Assume this returns a URL
                        $lims_customer_data->save();

                        return response()->json([
                            'payment_method' => 'pesapal',
                            'redirect_url' => $redirectUrl,
                        ]);
                    }
                }
            }
        }


    //sms send start
    $smsData = [];

    $smsTemplate = SmsTemplate::where('is_default',1)->latest()->first();
    $smsProvider = ExternalService::where('active',true)->where('type','sms')->first();
    if($smsProvider && $smsTemplate && $lims_pos_setting_data['send_sms'] == 1) {
        $smsData['type'] = 'onsite';
        $smsData['template_id'] = $smsTemplate['id'];
        $smsData['sale_status'] = $data['sale_status'];
        $smsData['payment_status'] = $data['payment_status'];
        $smsData['customer_id'] = $data['customer_id'];
        $smsData['reference_no'] = $data['reference_no'];
        $this->_smsModel->initialize($smsData);
    }
    //sms send end

    //api calling code
    // if($lims_sale_data->sale_status == '1' && isset($data['draft']) && $data['draft'])
    //     return redirect('sales/gen_invoice/'.$lims_sale_data->id);
    if($lims_sale_data->sale_status == '1') // sale status completed
        return $lims_sale_data->id;
    elseif(in_array('restaurant',explode(',',$general_setting->modules)) && $lims_sale_data->sale_status == '5')
        return $lims_sale_data->id;
    elseif($data['pos'])
        return redirect('pos')->with('message', $message);
    else
        return redirect('sales')->with('message', $message);

}