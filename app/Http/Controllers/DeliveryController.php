<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Product_Sale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductBatch;
use App\Models\Delivery;
use App\Models\DeliveryProduct;
use App\Models\Warehouse;
use App\Services\DeliveryProgress;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use DB;
use Auth;
use App\Mail\DeliveryDetails;
use App\Mail\DeliveryChallan;
use Mail;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Cache;
use App\Models\Courier;

class DeliveryController extends Controller
{
    use \App\Traits\MailInfo;

    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('delivery')) {
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $lims_delivery_all = Delivery::orderBy('id', 'desc')->where('user_id', Auth::id())->get();
            else
                $lims_delivery_all = Delivery::orderBy('id', 'desc')->get();
            $lims_courier_list = Courier::where('is_active', true)->get();
            return view('backend.delivery.index', compact('lims_delivery_all', 'lims_courier_list'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function deliveryListData(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);

        $columns = array(
            1 => 'reference_no'
        );
        if($role->hasPermissionTo('delivery')) {
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $lims_delivery_all = Delivery::orderBy('id', 'desc')->where('user_id', Auth::id())->get();
            else
                $lims_delivery_all = Delivery::orderBy('id', 'desc')->get();
            $lims_courier_list = Courier::where('is_active', true)->get();
        }

        if($request->input('length') != -1)
            $limit = $request->input('length');
        // $order = $columns[$request->input('order.0.column')];
        // $dir = $request->input('order.0.dir');

          $start = $request->input('start');
        // $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        if($request->input('search.value')) {
            $search = $request->input('search.value');
            $totalData = Delivery::where([
                ['reference_no', 'LIKE', "%{$search}%"]
            ])->count();
            // $lims_delivery_all = Delivery::where([
            //                         ['reference_no', 'LIKE', "%{$search}%"]
            //                     ])->get();
            $lims_delivery_all =  Delivery::select('deliveries.*')
                                            ->leftJoin('sales', 'deliveries.sale_id', '=', 'sales.id')
                                            ->leftJoin('packing_slips', 'deliveries.packing_slip_ids', '=', 'packing_slips.id') // Adjust if multiple ids
                                            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id') // Join the customers table via sales
                                            ->where(function($q) use ($search) {
                                                $q->where('deliveries.reference_no', 'LIKE', "%{$search}%")      // Search in deliveries reference_no
                                                ->orWhere('sales.reference_no', 'LIKE', "%{$search}%")         // Search in sales reference_no
                                                ->orWhere('packing_slips.reference_no', 'LIKE', "%{$search}%") // Search in packing_slips reference_no
                                                ->orWhere('deliveries.packing_slip_ids', 'LIKE', "%{$search}%")// Search in packing_slip_ids
                                                ->orWhere('customers.name', 'LIKE', "%{$search}%")             // Search in customer name
                                                ->orWhere('customers.phone_number', 'LIKE', "%{$search}%");    // Search in customer phone number
                                            })->get();
        }
        else {
            $totalData = Delivery::count();
            $lims_delivery_all = Delivery::get();
        }
        $totalFiltered = $totalData;
        $data = [];
        foreach ($lims_delivery_all as $key=>$delivery)
        {
            $customer_sale = DB::table('sales')
                            ->join('customers', 'sales.customer_id', '=', 'customers.id')
                            ->where('sales.id', $delivery->sale_id)
                            ->select('sales.reference_no','customers.name', 'customers.phone_number', 'customers.city', 'sales.grand_total')
                            ->get();

            $product_names = DB::table('delivery_products')
                            ->leftJoin('products', 'products.id', '=', 'delivery_products.product_id')
                            ->where('delivery_products.delivery_id', $delivery->id)
                            ->select('products.name', 'delivery_products.qty', 'delivery_products.product_id')
                            ->get()
                            ->map(function ($line) {
                                $name = $line->name ?: __('db.Removed product #:id', ['id' => $line->product_id]);
                                return $name.' × '.DeliveryProgress::formatQty((float) $line->qty);
                            })
                            ->all();
            if(!$product_names) {
                $product_names = DB::table('sales')
                            ->join('product_sales', 'sales.id', '=', 'product_sales.sale_id')
                            ->leftJoin('products', 'products.id', '=', 'product_sales.product_id')
                            ->where('sales.id', $delivery->sale_id)
                            ->pluck('products.name')
                            ->toArray();
            }
            if($delivery->packing_slip_ids)
                $packing_slip_references = \App\Models\PackingSlip::whereIn('id', explode(",", $delivery->packing_slip_ids))->pluck('reference_no')->toArray();
            else
                $packing_slip_references[0] = 'N/A';

            if($delivery->status == 1)
                $status = __('db.Packing');
            elseif($delivery->status == 2)
                $status = __('db.Delivering');
            else
                $status = __('db.Delivered');

            $barcode = \DNS2D::getBarcodePNG($delivery->reference_no, 'QRCODE');
            if($delivery->sale)
            {
                $nestedData['key'] = count($data);
                $nestedData['reference_no'] = $delivery->reference_no;
                $nestedData['sale_reference'] = $customer_sale[0]->reference_no;
                $nestedData['packing_slip_references'] = implode(",", $packing_slip_references);
                $nestedData['customer'] = $customer_sale[0]->name .'<br>'.$customer_sale[0]->phone_number;
                $nestedData['courier'] = $delivery->courier_id ? $delivery->courier->name : 'N/A';
                $nestedData['address'] = $delivery->address;
                $nestedData['products'] = implode(",", $product_names);
                $nestedData['grand_total'] = number_format($customer_sale[0]->grand_total, 2);
                if($delivery->status == 1)
                    $nestedData['status'] = '<div class="badge badge-primary">'.__('db.Packing').'</div>';
                elseif($delivery->status == 2)
                    $nestedData['status'] = '<div class="badge badge-primary">'.__('db.Delivering').'</div>';
                else
                    $nestedData['status'] = '<div class="badge badge-primary">'.__('db.Delivered').'</div>';
                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.__("db.action").'
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <button type="button" data-id="'.$delivery->id.'" class="open-EditCategoryDialog btn btn-link" data-toggle="modal" data-target="#editModal" ><i class="dripicons-document-edit"></i> '.__("db.edit").'</button>
                                </li>
                                <li class="divider"></li>'.
                                \Form::open(["route" => ["delivery.delete", $delivery->id], "method" => "POST"] ).'
                                <li>
                                  <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> '.__("db.delete").'</button>
                                </li>'.\Form::close().'
                            </ul>
                        </div>';
            }
            $data[] = $nestedData;
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );

        echo json_encode($json_data);
    }

    public function create($id){
        if(!$this->canManageDelivery()) {
            return response()->json(['error' => __('db.Sorry! You are not allowed to access this module')], 403);
        }

        $sale = Sale::find($id);
        if(!$sale) {
            return response()->json(['error' => __('db.Sale not found')], 404);
        }

        $customer = Customer::find($sale->customer_id);
        $delivery_data[] = $this->nextReference();
        $delivery_data[] = $sale->reference_no;
        $delivery_data[] = '1';
        $delivery_data[] = '';
        $delivery_data[] = '';
        $delivery_data[] = optional($customer)->name ?? '';
        if(in_array('ecommerce', explode(',',config('addons'))) || in_array('restaurant', explode(',',config('addons')))) {
            $delivery_data[] = trim(($sale->shipping_address ?? '').' '.($sale->shipping_city ?? '').' '.($sale->shipping_country ?? ''));
        } else {
            $delivery_data[] = trim((optional($customer)->address ?? '').' '.(optional($customer)->city ?? '').' '.(optional($customer)->country ?? ''));
        }
        $delivery_data[] = '';
        $delivery_data[] = '';
        $delivery_data[] = $this->linePayload((int) $id);

        return $delivery_data;
    }

    public function store(Request $request)
    {
        if(!$this->canManageDelivery()) {
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
        }

        $data = $request->except('file', 'deliver_qty', 'deliver_lines');
        $saleId = (int) $request->input('sale_id');
        try {
            $delivery = DB::transaction(function () use ($request, $data, $saleId) {
                $sale = Sale::where('id', $saleId)->lockForUpdate()->first();
                if(!$sale) {
                    throw new \InvalidArgumentException(__('db.Sale not found'));
                }
                Product_Sale::where('sale_id', $saleId)->lockForUpdate()->get();
                $accepted = DeliveryProgress::assertLines($saleId, $this->submittedQuantities($request));

                $reference = $data['reference_no'] ?? '';
                if(!$reference || Delivery::where('reference_no', $reference)->exists()) {
                    $reference = $this->nextReference();
                }

                $delivery = new Delivery();
                $delivery->reference_no = $reference;
                $delivery->sale_id = $saleId;
                $delivery->user_id = Auth::id();
                $delivery->courier_id = $data['courier_id'] ?? null;
                $delivery->address = $data['address'] ?? '';
                $delivery->delivered_by = $data['delivered_by'] ?? null;
                $delivery->recieved_by = $data['recieved_by'] ?? null;
                $delivery->status = $data['status'] ?? '1';
                $delivery->note = $data['note'] ?? null;
                $document = $request->file;
                if ($document) {
                    $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
                    $documentName = $reference . '.' . $ext;
                    $document->move(public_path('documents/delivery'), $documentName);
                    $delivery->file = $documentName;
                }
                $delivery->save();

                foreach ($accepted as $line) {
                    DeliveryProduct::create([
                        'delivery_id' => $delivery->id,
                        'product_sale_id' => $line['product_sale_id'],
                        'product_id' => $line['product_id'],
                        'qty' => $line['qty'],
                    ]);
                }
                DeliveryProgress::syncFlags($saleId);

                return $delivery;
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('not_permitted', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('not_permitted', __('db.Delivery could not be saved.').' '.$e->getMessage());
        }

        $message = $this->notifyCustomer($delivery, $data['status'] ?? '1');
        $sale = Sale::find($delivery->sale_id);
        $saleDate = $sale ? $sale->created_at->toDateString() : date('Y-m-d');

        return redirect()->to(url('sales').'?'.http_build_query([
            'open_sale' => $delivery->sale_id,
            'starting_date' => $saleDate,
            'ending_date' => $saleDate,
        ]))->with('message', $message);
    }

    public function productDeliveryData($id)
    {
        $lims_delivery_data = Delivery::find($id);
        if(!$lims_delivery_data || !$lims_delivery_data->sale) {
            return [];
        }
        $saleId = $lims_delivery_data->sale->id;
        $lineQty = DeliveryProduct::where('delivery_id', $lims_delivery_data->id)->pluck('qty', 'product_sale_id');
        $remaining = [];
        foreach (DeliveryProgress::linesForSale((int) $saleId) as $line) {
            $remaining[$line['product_sale_id']] = $line['remaining'];
        }
        $lims_product_sale_data = Product_Sale::where('sale_id', $saleId)->get();
        $product_sale = [[], [], [], [], [], []];

        foreach ($lims_product_sale_data as $key => $product_sale_data) {
            if($lineQty->isNotEmpty() && !$lineQty->has($product_sale_data->id)) {
                continue;
            }
            $product = Product::select('name', 'code')->find($product_sale_data->product_id);
            $code = $product->code ?? ('#'.$product_sale_data->product_id);
            $name = $product->name ?? __('db.Removed product #:id', ['id' => $product_sale_data->product_id]);
            if($product && $product_sale_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)->first();
                if($lims_product_variant_data) {
                    $code = $lims_product_variant_data->item_code;
                }
            }
            if($product_sale_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no', 'expired_date')->find($product_sale_data->product_batch_id);
                if($product_batch_data) {
                    $batch_no = $product_batch_data->batch_no;
                    $expired_date = date(config('date_format'), strtotime($product_batch_data->expired_date));
                }
                else {
                    $batch_no = 'N/A';
                    $expired_date = 'N/A';
                }
            }
            else {
                $batch_no = 'N/A';
                $expired_date = 'N/A';
            }
            $index = count($product_sale[0]);
            $product_sale[0][$index] = $code;
            $product_sale[1][$index] = $name;
            $product_sale[2][$index] = $batch_no;
            $product_sale[3][$index] = $expired_date;
            $product_sale[4][$index] = $lineQty->has($product_sale_data->id)
                ? DeliveryProgress::formatQty((float) $lineQty[$product_sale_data->id])
                : $product_sale_data->qty;
            $product_sale[5][$index] = DeliveryProgress::formatQty((float) ($remaining[$product_sale_data->id] ?? 0));
        }
        return $product_sale;
    }

    public function sendMail(Request $request)
    {
        $data = $request->all();
        $lims_delivery_data = Delivery::find($data['delivery_id']);
        $lims_sale_data = Sale::find($lims_delivery_data->sale->id);
        $lims_product_sale_data = Product_Sale::where('sale_id', $lims_delivery_data->sale->id)->get();
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $mail_setting = MailSetting::latest()->first();
        if($lims_customer_data->email && $mail_setting) {
            //collecting male data
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['date'] = date(config('date_format'), strtotime($lims_delivery_data->created_at->toDateString()));
            $mail_data['delivery_reference_no'] = $lims_delivery_data->reference_no;
            $mail_data['sale_reference_no'] = $lims_sale_data->reference_no;
            $mail_data['status'] = $lims_delivery_data->status;
            $mail_data['customer_name'] = $lims_customer_data->name;
            $mail_data['address'] = $lims_customer_data->address . ', '.$lims_customer_data->city;
            $mail_data['phone_number'] = $lims_customer_data->phone_number;
            $mail_data['note'] = $lims_delivery_data->note;
            $mail_data['prepared_by'] = $lims_delivery_data->user->name;
            if($lims_delivery_data->delivered_by)
                $mail_data['delivered_by'] = $lims_delivery_data->delivered_by;
            else
                $mail_data['delivered_by'] = 'N/A';
            if($lims_delivery_data->recieved_by)
                $mail_data['recieved_by'] = $lims_delivery_data->recieved_by;
            else
                $mail_data['recieved_by'] = 'N/A';
            //return $mail_data;

            foreach ($lims_product_sale_data as $key => $product_sale_data) {
                $lims_product_data = Product::select('code', 'name')->find($product_sale_data->product_id);
                $mail_data['codes'][$key] = $lims_product_data->code;
                $mail_data['name'][$key] = $lims_product_data->name;
                if($product_sale_data->variant_id) {
                    $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)->first();
                    $mail_data['codes'][$key] = $lims_product_variant_data->item_code;
                }
                $mail_data['qty'][$key] = $product_sale_data->qty;
            }
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new DeliveryChallan($mail_data));
                $message = 'Mail sent successfully';
            }
            catch(\Exception $e){
                $message = 'Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        else
            $message = 'Customer does not have email!';

        return redirect()->back()->with('message', $message);
    }

    public function edit($id)
    {
        if(!$this->canManageDelivery()) {
            return response()->json(['error' => __('db.Sorry! You are not allowed to access this module')], 403);
        }
        $lims_delivery_data = Delivery::find($id);
        if(!$lims_delivery_data) {
            return response()->json(['error' => 'Delivery not found'], 404);
        }
        $customer_sale = DB::table('sales')->join('customers', 'sales.customer_id', '=', 'customers.id')->where('sales.id', $lims_delivery_data->sale_id)->select('sales.reference_no','customers.name')->get();

        $delivery_data[] = $lims_delivery_data->reference_no;
        $delivery_data[] = $customer_sale[0]->reference_no ?? '';
        $delivery_data[] = $lims_delivery_data->status;
        $delivery_data[] = $lims_delivery_data->delivered_by;
        $delivery_data[] = $lims_delivery_data->recieved_by;
        $delivery_data[] = $customer_sale[0]->name ?? '';
        $delivery_data[] = $lims_delivery_data->address;
        $delivery_data[] = $lims_delivery_data->note;
        $delivery_data[] = $lims_delivery_data->courier_id;
        $current = DeliveryProduct::where('delivery_id', $lims_delivery_data->id)->pluck('qty', 'product_sale_id');
        $lines = $this->linePayload((int) $lims_delivery_data->sale_id, (int) $lims_delivery_data->id);
        foreach ($lines as &$line) {
            $currentQty = isset($current[$line['product_sale_id']]) ? (float) $current[$line['product_sale_id']] : 0;
            $line['current_qty'] = $currentQty;
            $line['max_qty'] = $line['remaining'];
            $saleDelivered = $line['delivered'] + $currentQty;
            $saleRemaining = max(0, $line['sold'] - $saleDelivered);
            $line['delivered'] = $saleDelivered;
            $line['remaining'] = $saleRemaining;
            $line['delivered_label'] = DeliveryProgress::formatQty($saleDelivered);
            $line['remaining_label'] = DeliveryProgress::formatQty($saleRemaining);
        }
        unset($line);
        $delivery_data[] = $lines;
        return $delivery_data;
    }

    public function update(Request $request)
    {
        if(!$this->canManageDelivery()) {
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
        }

        $input = $request->except('file', 'deliver_qty', 'deliver_lines', 'track_lines');
        $lims_delivery_data = Delivery::find($input['delivery_id']);
        if(!$lims_delivery_data) {
            return redirect()->back()->with('not_permitted', __('db.Delivery not found'));
        }

        try {
            DB::transaction(function () use ($request, $input, $lims_delivery_data) {
                Product_Sale::where('sale_id', $lims_delivery_data->sale_id)->lockForUpdate()->get();
                $hasLines = DeliveryProduct::where('delivery_id', $lims_delivery_data->id)->exists();
                if($request->input('track_lines')) {
                    try {
                        $accepted = DeliveryProgress::assertLines(
                            (int) $lims_delivery_data->sale_id,
                            $this->submittedQuantities($request),
                            (int) $lims_delivery_data->id
                        );
                    } catch (\InvalidArgumentException $e) {
                        $emptySelection = __('db.Select at least one product and a quantity that is still remaining.');
                        if($hasLines || $e->getMessage() !== $emptySelection) {
                            throw $e;
                        }
                        $accepted = [];
                    }
                    if($accepted) {
                        DeliveryProduct::where('delivery_id', $lims_delivery_data->id)->delete();
                        foreach ($accepted as $line) {
                            DeliveryProduct::create([
                                'delivery_id' => $lims_delivery_data->id,
                                'product_sale_id' => $line['product_sale_id'],
                                'product_id' => $line['product_id'],
                                'qty' => $line['qty'],
                            ]);
                        }
                    }
                }

                $document = $request->file;
                if ($document) {
                    $this->fileDelete(public_path('documents/delivery/'), $lims_delivery_data->file);
                    $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
                    $documentName = $input['reference_no'] . '.' . $ext;
                    $document->move(public_path('documents/delivery'), $documentName);
                    $input['file'] = $documentName;
                }
                $lims_delivery_data->update($input);
                DeliveryProgress::syncFlags((int) $lims_delivery_data->sale_id);
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('not_permitted', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('not_permitted', __('db.Delivery could not be updated.').' '.$e->getMessage());
        }

        $lims_delivery_data->refresh();
        $message = __('db.Delivery updated successfully');
        $mailMessage = $this->notifyCustomer($lims_delivery_data, $input['status'] ?? $lims_delivery_data->status, $message);
        return redirect('delivery')->with('message', $mailMessage);
    }

    public function deleteBySelection(Request $request)
    {
        if(!$this->canManageDelivery()) {
            return __('db.Sorry! You are not allowed to access this module');
        }
        $delivery_id = $request['deliveryIdArray'];
        foreach ($delivery_id as $id) {
            $this->removeDelivery((int) $id);
        }
        return __('db.Delivery deleted successfully');
    }

    public function delete($id)
    {
        if(!$this->canManageDelivery()) {
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
        }
        $this->removeDelivery((int) $id);

        return redirect('delivery')->with('message', __('db.Delivery deleted successfully'));
    }

    public function report(Request $request)
    {
        if(!$this->canManageDelivery()) {
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
        }

        [$start_date, $end_date, $year, $month] = $this->reportPeriod($request);
        $warehouse_id = (int) $request->input('warehouse_id', 0);
        $progress = $request->input('progress', 'all');

        $saleQuery = Sale::query()
            ->whereNotIn('sales.sale_status', [3, 4]);
        if($start_date && $end_date) {
            $saleQuery->whereDate('sales.created_at', '>=', $start_date)
                ->whereDate('sales.created_at', '<=', $end_date);
        }
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $saleQuery->where('sales.user_id', Auth::id());
        } elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse') {
            $saleQuery->where('sales.warehouse_id', Auth::user()->warehouse_id);
        }
        if($warehouse_id) {
            $saleQuery->where('sales.warehouse_id', $warehouse_id);
        }

        $sales = $saleQuery->with('customer')->orderByDesc('sales.id')->get();
        $progressMap = DeliveryProgress::forSales($sales->pluck('id'));

        $summary = [
            'sales' => $sales->count(),
            'not_delivered' => 0,
            'partial' => 0,
            'full' => 0,
            'in_progress' => 0,
            'sold' => 0,
            'delivered' => 0,
            'remaining' => 0,
        ];
        $saleRows = [];
        foreach ($sales as $sale) {
            $row = $progressMap[$sale->id] ?? [
                'status' => DeliveryProgress::NOT_DELIVERED,
                'sold' => 0,
                'delivered' => 0,
                'remaining' => 0,
            ];
            $summary['sold'] += $row['sold'];
            $summary['delivered'] += $row['delivered'];
            $summary['remaining'] += $row['remaining'];
            $summary[$row['status'] === DeliveryProgress::NOT_DELIVERED ? 'not_delivered' : $row['status']]++;
            if($progress !== 'all' && $row['status'] !== $progress) {
                continue;
            }
            $saleRows[] = [
                'id' => $sale->id,
                'date' => date(config('date_format'), strtotime($sale->created_at)),
                'reference_no' => $sale->reference_no,
                'customer' => optional($sale->customer)->name ?? '',
                'status' => $row['status'],
                'label' => DeliveryProgress::label($row['status']),
                'sold' => $row['sold'],
                'delivered' => $row['delivered'],
                'remaining' => $row['remaining'],
                'invoice' => route('sale.invoice', $sale->id),
            ];
        }

        $deliveryQuery = Delivery::query()
            ->join('sales', 'sales.id', '=', 'deliveries.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereNull('sales.deleted_at');
        if($start_date && $end_date) {
            $deliveryQuery->whereDate('deliveries.created_at', '>=', $start_date)
                ->whereDate('deliveries.created_at', '<=', $end_date);
        }
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $deliveryQuery->where('deliveries.user_id', Auth::id());
        } elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse') {
            $deliveryQuery->where('sales.warehouse_id', Auth::user()->warehouse_id);
        }
        if($warehouse_id) {
            $deliveryQuery->where('sales.warehouse_id', $warehouse_id);
        }
        $deliveries = $deliveryQuery
            ->orderByDesc('deliveries.id')
            ->get([
                'deliveries.id',
                'deliveries.reference_no',
                'deliveries.sale_id',
                'deliveries.status',
                'deliveries.note',
                'deliveries.created_at',
                'sales.reference_no as sale_reference',
                'customers.name as customer_name',
            ]);

        $deliveryIds = $deliveries->pluck('id')->all();
        $lineRows = $deliveryIds
            ? DB::table('delivery_products')
                ->leftJoin('products', 'products.id', '=', 'delivery_products.product_id')
                ->whereIn('delivery_id', $deliveryIds)
                ->get(['delivery_id', 'delivery_products.qty', 'products.name'])
            : collect();
        $linesByDelivery = [];
        foreach ($lineRows as $line) {
            $linesByDelivery[$line->delivery_id][] = ($line->name ?: __('db.Removed product')).' × '.DeliveryProgress::formatQty((float) $line->qty);
        }

        $awaiting = [];
        if($progress === 'all' || $progress === DeliveryProgress::NOT_DELIVERED || $progress === DeliveryProgress::PARTIAL || $progress === DeliveryProgress::IN_PROGRESS) {
            foreach ($saleRows as $saleRow) {
                if($saleRow['remaining'] <= 0.00001) {
                    continue;
                }
                foreach (DeliveryProgress::linesForSale((int) $saleRow['id']) as $line) {
                    if($line['remaining'] <= 0.00001) {
                        continue;
                    }
                    $awaiting[] = [
                        'sale' => $saleRow['reference_no'],
                        'date' => $saleRow['date'],
                        'customer' => $saleRow['customer'],
                        'product' => $line['name'],
                        'code' => $line['code'],
                        'sold' => $line['sold'],
                        'delivered' => $line['delivered'],
                        'remaining' => $line['remaining'],
                    ];
                }
            }
        }

        $warehouses = Warehouse::where('is_active', true)->get();
        $years = $this->reportYears();
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = __('db.month_'.$i);
        }

        return view('backend.delivery.report', compact(
            'start_date',
            'end_date',
            'year',
            'month',
            'years',
            'months',
            'warehouse_id',
            'progress',
            'summary',
            'saleRows',
            'deliveries',
            'linesByDelivery',
            'awaiting',
            'warehouses'
        ));
    }

    /**
     * Year and month define the period when either one is chosen.
     * A month without a year uses the current year.
     * All years and all months show the complete report, unless both
     * date fields are filled and no year or month was posted (legacy date filter).
     *
     * @return array{0:?string,1:?string,2:string,3:string}
     */
    private function reportPeriod(Request $request): array
    {
        $postedYear = $request->has('year');
        $postedMonth = $request->has('month');

        if(!$postedYear && !$postedMonth) {
            if($request->filled('start_date') || $request->filled('end_date')) {
                $start = $request->input('start_date') ?: '2000-01-01';
                $end = $request->input('end_date') ?: date('Y-m-d');
                if($start > $end) {
                    [$start, $end] = [$end, $start];
                }

                return [$start, $end, 'all', 'all'];
            }

            $year = (int) date('Y');
            $month = (int) date('n');

            return $this->monthBounds($year, $month);
        }

        $yearValue = (string) $request->input('year', 'all');
        $monthValue = (string) $request->input('month', 'all');
        $yearAll = ($yearValue === '' || $yearValue === 'all');
        $monthAll = ($monthValue === '' || $monthValue === 'all' || (int) $monthValue < 1 || (int) $monthValue > 12);

        if(!$monthAll && $yearAll) {
            $yearValue = date('Y');
            $yearAll = false;
        }

        if(!$yearAll) {
            $year = (int) $yearValue;
            if(!$monthAll) {
                return $this->monthBounds($year, (int) $monthValue);
            }

            return [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year), (string) $year, 'all'];
        }

        if($request->filled('start_date') && $request->filled('end_date')) {
            $start = $request->input('start_date');
            $end = $request->input('end_date');
            if($start > $end) {
                [$start, $end] = [$end, $start];
            }

            return [$start, $end, 'all', 'all'];
        }

        return [null, null, 'all', 'all'];
    }

    /**
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function monthBounds(int $year, int $month): array
    {
        $month = max(1, min(12, $month));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start.' 12:00:00'));

        return [$start, $end, (string) $year, (string) $month];
    }

    private function reportYears(): array
    {
        $fromDeliveries = DB::table('deliveries')
            ->whereNotNull('created_at')
            ->selectRaw('YEAR(created_at) as y')
            ->distinct()
            ->pluck('y');
        $fromSales = DB::table('sales')
            ->whereNull('deleted_at')
            ->whereNotNull('created_at')
            ->selectRaw('YEAR(created_at) as y')
            ->distinct()
            ->pluck('y');

        return $fromDeliveries
            ->merge($fromSales)
            ->map(fn ($year) => (int) $year)
            ->filter(fn ($year) => $year >= 2000)
            ->push((int) date('Y'))
            ->push((int) date('Y') + 1)
            ->unique()
            ->sort()
            ->reverse()
            ->values()
            ->all();
    }

    private function submittedQuantities(Request $request): array
    {
        $lines = $request->input('deliver_lines', []);
        if (is_array($lines) && $lines !== []) {
            return array_values($lines);
        }

        return (array) $request->input('deliver_qty', []);
    }

    private function canManageDelivery(): bool
    {
        $role = Role::find(Auth::user()->role_id);

        return $role && $role->hasPermissionTo('delivery');
    }

    private function nextReference(): string
    {
        do {
            $reference = 'dr-'.date('Ymd-His').'-'.random_int(100, 999);
        } while (Delivery::where('reference_no', $reference)->exists());

        return $reference;
    }

    private function linePayload(int $saleId, ?int $exceptDeliveryId = null): array
    {
        $lines = [];
        foreach (DeliveryProgress::linesForSale($saleId, $exceptDeliveryId) as $line) {
            $lines[] = [
                'product_sale_id' => $line['product_sale_id'],
                'name' => $line['name'],
                'code' => $line['code'],
                'sold' => $line['sold'],
                'delivered' => $line['delivered'],
                'remaining' => $line['remaining'],
                'sold_label' => DeliveryProgress::formatQty($line['sold']),
                'delivered_label' => DeliveryProgress::formatQty($line['delivered']),
                'remaining_label' => DeliveryProgress::formatQty($line['remaining']),
            ];
        }

        return $lines;
    }

    private function notifyCustomer(Delivery $delivery, $status, ?string $success = null): string
    {
        $success = $success ?? __('db.Delivery created successfully');
        $sale = Sale::find($delivery->sale_id);
        $customer = $sale ? Customer::find($sale->customer_id) : null;
        $message = $success;
        $mail_setting = MailSetting::latest()->first();
        if($customer && $customer->email && $status != 1 && $mail_setting) {
            $mail_data['email'] = $customer->email;
            $mail_data['customer'] = $customer->name;
            $mail_data['sale_reference'] = $sale->reference_no;
            $mail_data['delivery_reference'] = $delivery->reference_no;
            $mail_data['status'] = $status;
            $mail_data['address'] = $delivery->address;
            $mail_data['delivered_by'] = $delivery->delivered_by;
            $this->setMailInfo($mail_setting);
            try {
                Mail::to($mail_data['email'])->send(new DeliveryDetails($mail_data));
            } catch (\Exception $e) {
                $message = $success.'. '.__('db.Please setup your :link to send mail.', [
                    'link' => '<a href="setting/mail_setting">'.e(__('db.mail setting')).'</a>',
                ]);
            }
        }

        return $message;
    }

    private function removeDelivery(int $id): void
    {
        $delivery = Delivery::find($id);
        if(!$delivery) {
            return;
        }
        $saleId = (int) $delivery->sale_id;
        $this->fileDelete(public_path('documents/delivery/'), $delivery->file);
        DeliveryProduct::where('delivery_id', $delivery->id)->delete();
        $delivery->delete();
        if($saleId) {
            DeliveryProgress::syncFlags($saleId);
        }
    }
}
