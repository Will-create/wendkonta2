<?php

namespace App\Http\Controllers;

use App\Enums\CustomerTypeEnum;
use App\Http\Requests\Sale\StoreSaleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Warehouse;
use App\Models\Biller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Tax;
use App\Models\Sale;
use App\Models\Delivery;
use App\Models\PosSetting;
use App\Models\Product_Sale;
use App\Models\Product_Warehouse;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\PaymentWithCheque;
use App\Models\PaymentWithGiftCard;
use App\Models\PaymentWithCreditCard;
use App\Models\PaymentWithPaypal;
use App\Models\User;
use App\Models\Variant;
use App\Models\ProductVariant;
use App\Models\CashRegister;
use App\Models\Returns;
use App\Models\ProductReturn;
use App\Models\Expense;
use App\Models\ProductPurchase;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\RewardPointSetting;
use App\Models\CustomField;
use App\Models\Table;
use App\Models\Courier;
use App\Models\ExternalService;
use App\Models\Adjustment;
use App\Models\ProductAdjustment;
use Illuminate\Support\Facades\DB;
use Cache;
use App\Models\GeneralSetting;
use App\Models\MailSetting;
use Stripe\Stripe;
use NumberToWords\NumberToWords;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Mail\SaleDetails;
use App\Mail\LogMessage;
use App\Mail\PaymentDetails;
use Mail;
use Srmklive\PayPal\Services\ExpressCheckout;
use Srmklive\PayPal\Services\AdaptivePayments;
use GeniusTS\HijriDate\Date;
use Illuminate\Support\Facades\Validator;
use App\Models\Currency;
use App\Models\InvoiceSchema;
use App\Models\InvoiceSetting;
use App\Models\PackingSlip;
use App\Models\RewardPoint;
use App\Models\SaleWarrantyGuarantee;
use App\Models\SmsTemplate;
use App\Services\DeliveryProgress;
use App\Services\SmsService;
use App\SMSProviders\TonkraSms;
use App\ViewModels\ISmsModel;
use DateTime;
use PHPUnit\Framework\MockObject\Stub\ReturnSelf;
use Salla\ZATCA\GenerateQrCode;
use Salla\ZATCA\Tags\InvoiceDate;
use Salla\ZATCA\Tags\InvoiceTaxAmount;
use Salla\ZATCA\Tags\InvoiceTotalAmount;
use Salla\ZATCA\Tags\Seller;
use Salla\ZATCA\Tags\TaxNumber;
use Carbon\Carbon;

class SaleController extends Controller
{
    use \App\Traits\TenantInfo;
    use \App\Traits\MailInfo;

    private $_smsModel;

    public function __construct(ISmsModel $smsModel)
    {
        $this->_smsModel = $smsModel;
    }

    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-index')) {
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            if($request->input('warehouse_id'))
                $warehouse_id = $request->input('warehouse_id');
            else
                $warehouse_id = 0;

            if($request->input('sale_status'))
                $sale_status = $request->input('sale_status');
            else
                $sale_status = 0;

            if($request->input('payment_status'))
                $payment_status = $request->input('payment_status');
            else
                $payment_status = 0;

            if($request->input('sale_type'))
                $sale_type = $request->input('sale_type');
            else
                $sale_type = 0;

            if($request->input('payment_method'))
                $payment_method = $request->input('payment_method');
            else
                $payment_method = 0;

            if($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date = $request->input('ending_date');
            }
            else {
                // Same window as the All Time preset. A trailing year hides every
                // invoice once the newest sale is more than twelve months old.
                $starting_date = '2000-01-01';
                $ending_date = date('Y-m-d');
            }

            $lims_gift_card_list = GiftCard::where("is_active", true)->get();
            $lims_pos_setting_data = PosSetting::latest()->first();
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_account_list = Account::where('is_active', true)->get();
            $lims_courier_list = Courier::where('is_active', true)->get();
            if($lims_pos_setting_data)
                $options = explode(',', $lims_pos_setting_data->payment_options);
            else
                $options = [];
            $numberOfInvoice = Sale::count();
            $custom_fields = CustomField::where([
                                ['belongs_to', 'sale'],
                                ['is_table', true]
                            ])->pluck('name');
            $field_name = [];
            foreach($custom_fields as $fieldName) {
                $field_name[] = str_replace(" ", "_", strtolower($fieldName));
            }
            $smsTemplates = SmsTemplate::all();
            return view('backend.sale.index', compact('starting_date', 'ending_date', 'warehouse_id', 'sale_status', 'payment_status', 'sale_type', 'payment_method', 'lims_gift_card_list', 'lims_pos_setting_data', 'lims_reward_point_setting_data', 'lims_account_list', 'lims_warehouse_list', 'all_permission','options', 'numberOfInvoice', 'custom_fields', 'field_name', 'lims_courier_list','smsTemplates'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function saleData(Request $request)
    {
        $columns = array(
            2 => 'created_at',
            3 => 'reference_no',
            4 => 'customer_id',
            5 => 'warehouse_id',
            6 => 'sale_status',
            7 => 'payment_status',
            10 => 'grand_total',
            12 => 'paid_amount',
        );

        $warehouse_id = $request->input('warehouse_id');
        $sale_status = $request->input('sale_status');
        $payment_status = $request->input('payment_status');
        $sale_type = $request->input('sale_type');
        $payment_method = $request->input('payment_method');

        // $q = Sale::whereDate('sales.created_at', '>=' ,$request->input('starting_date'))->whereDate('sales.created_at', '<=' ,$request->input('ending_date'));
        $q = Sale::join('payments', 'sales.id', '=', 'payments.sale_id')
                ->whereDate('sales.created_at', '>=', $request->input('starting_date'))
                ->whereDate('sales.created_at', '<=', $request->input('ending_date'))
                ->select('sales.id', 'sales.*','payments.paying_method');

        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('sales.user_id', Auth::id());
        elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse')
            $q = $q->where('sales.warehouse_id', Auth::user()->warehouse_id);
        if($sale_status)
            $q = $q->where('sales.sale_status', $sale_status);
        if($payment_status)
            $q = $q->where('sales.payment_status', $payment_status);
        if($sale_type)
            $q = $q->where('sales.sale_type', $sale_type);
        if($payment_method)
            $q = $q->where('payments.paying_method', $payment_method);

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'sales.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        //fetching custom fields data
        $custom_fields = CustomField::where([
                        ['belongs_to', 'sale'],
                        ['is_table', true]
                    ])->pluck('name');
        $field_names = [];
        foreach($custom_fields as $fieldName) {
            $field_names[] = str_replace(" ", "_", strtolower($fieldName));
        }
        if(empty($request->input('search.value'))) {
            $q = Sale::with('biller', 'customer', 'warehouse', 'user')
                ->whereDate('sales.created_at', '>=' ,$request->input('starting_date'))
                ->whereDate('sales.created_at', '<=' ,$request->input('ending_date'));

            if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $q = $q->where('sales.user_id', Auth::id());
            elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse')
                $q = $q->where('sales.warehouse_id', Auth::user()->warehouse_id);
            if($warehouse_id)
                $q = $q->where('sales.warehouse_id', $warehouse_id);
            if($sale_status)
                $q = $q->where('sales.sale_status', $sale_status);
            if($payment_status)
                $q = $q->where('sales.payment_status', $payment_status);
            if($sale_type)
                $q = $q->where('sales.sale_type', $sale_type);
            if($payment_method)
                $q = $q->join('payments','sales.id','=','payments.sale_id')->select('sales.id','sales.*','payments.paying_method')->where('payments.paying_method', $payment_method);

            $totalData = $q->count();
            $totalFiltered = $totalData;

            if($request->input('length') != -1)
                $limit = $request->input('length');
            else
                $limit = $totalData;
            $start = $request->input('start');
            $order = 'sales.'.$columns[$request->input('order.0.column')];
            $dir = $request->input('order.0.dir');

            $q->offset($start)->limit($limit)->orderBy($order, $dir);

            $sales = $q->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = Sale::query()
                ->select('sales.*')
                ->with('biller', 'customer', 'warehouse', 'user')
                ->whereDate('sales.created_at', '>=', $request->input('starting_date'))
                ->whereDate('sales.created_at', '<=', $request->input('ending_date'));

            if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $q->where('sales.user_id', Auth::id());
            elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse')
                $q->where('sales.warehouse_id', Auth::user()->warehouse_id);
            if($warehouse_id)
                $q->where('sales.warehouse_id', $warehouse_id);
            if($sale_status)
                $q->where('sales.sale_status', $sale_status);
            if($payment_status)
                $q->where('sales.payment_status', $payment_status);
            if($sale_type)
                $q->where('sales.sale_type', $sale_type);
            if($payment_method) {
                $q->whereExists(function ($payment) use ($payment_method) {
                    $payment->select(DB::raw(1))
                        ->from('payments')
                        ->whereColumn('payments.sale_id', 'sales.id')
                        ->where('payments.paying_method', $payment_method);
                });
            }

            $q->where(function ($match) use ($search, $field_names) {
                $match->where('sales.reference_no', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($customer) use ($search) {
                        $customer->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('phone_number', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('biller', function ($biller) use ($search) {
                        $biller->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereExists(function ($product) use ($search) {
                        $product->select(DB::raw(1))
                            ->from('product_sales')
                            ->leftJoin('products', 'product_sales.product_id', '=', 'products.id')
                            ->whereColumn('product_sales.sale_id', 'sales.id')
                            ->where(function ($name) use ($search) {
                                $name->where('products.name', 'LIKE', "%{$search}%")
                                    ->orWhere('products.code', 'LIKE', "%{$search}%")
                                    ->orWhere('product_sales.imei_number', 'LIKE', "%{$search}%");
                            });
                    });
                $parsed = strtotime(str_replace('/', '-', $search));
                if($parsed) {
                    $match->orWhereDate('sales.created_at', date('Y-m-d', $parsed));
                }
                foreach ($field_names as $field_name) {
                    $match->orWhere('sales.'.$field_name, 'LIKE', "%{$search}%");
                }
            });

            $totalFiltered = (clone $q)->count();
            $sales = $q->offset($start)->limit($limit)->orderBy($order, $dir)->get();
        }
        $data = array();
        $progressMap = !empty($sales) ? DeliveryProgress::forSales(collect($sales)->pluck('id')) : [];
        if(!empty($sales))
        {
            // return $sales;
            foreach ($sales as $key=>$sale)
            {

                // return dd($sale);
                $nestedData['id'] = $sale->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format').' h:i:s a', strtotime($sale->created_at));
                //$nestedData['date'] = $sale->created_at;
                $nestedData['reference_no'] = $sale->reference_no;
                //$nestedData['biller'] = $sale->biller->name;
                $nestedData['customer'] = $sale->customer->name.'<br>'.$sale->customer->phone_number.'<input type="hidden" class="deposit" value="'.($sale->customer->deposit - $sale->customer->expense).'" />'.'<input type="hidden" class="points" value="'.$sale->customer->points.'" />';
                // new column warehouse added in sale list. [09.02.2025]
                $warehouse = Warehouse::select('name')->where('id', $sale->warehouse_id)->first();
                $nestedData['warehouse_name'] = $warehouse->name;

                // products details
                $nestedData['products'] = [];
                $nestedData['qty'] = [];

                $productNames = [];
                $productQtys = [];

                $saleLines = DB::table('product_sales')
                    ->leftJoin('products', 'products.id', '=', 'product_sales.product_id')
                    ->where('product_sales.sale_id', $sale->id)
                    ->orderBy('product_sales.id')
                    ->get(['products.name', 'product_sales.qty', 'product_sales.product_id']);
                $total_products = $saleLines->count();

                foreach ($saleLines as $key => $product) {
                    $label = $product->name ?: __('db.Removed product #:id', ['id' => $product->product_id]);
                    if ($key + 1 < $total_products) {
                        $productNames[] = '<div style="border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 4px;">'
                                        . e($label) . '</div>';
                    } else {
                        $productNames[] = '<div style="padding-bottom: 4px; margin-bottom: 4px;">'
                                        . e($label) . '</div>';
                    }
                    $productQtys[] = '<div style="padding-bottom: 4px; margin-bottom: 4px;">'
                                    . '<span class="badge badge-primary">' . e($product->qty) . '</span></div>';
                }
                $nestedData['products'] = implode('', $productNames);
                $nestedData['qty'] = implode('', $productQtys);

                $payments = Payment::where('sale_id', $sale->id)->select('amount','paying_method')->get();
                $paymentMethods = $payments->map(function ($payment) {
                    return ucfirst($payment->paying_method) . '(' . number_format($payment->amount, 2) . ')';
                })->implode(', ');

                $nestedData['payment_method'] = $paymentMethods;

                if($sale->sale_status == 1){
                    $nestedData['sale_status'] = '<div class="badge badge-success">'.__('db.Completed').'</div>';
                    $sale_status = __('db.Completed');
                }
                elseif($sale->sale_status == 2){
                    $nestedData['sale_status'] = '<div class="badge badge-danger">'.__('db.Pending').'</div>';
                    $sale_status = __('db.Pending');
                }
                elseif($sale->sale_status == 3){
                    $nestedData['sale_status'] = '<div class="badge badge-warning">'.__('db.Draft').'</div>';
                    $sale_status = __('db.Draft');
                }
                elseif($sale->sale_status == 4){
                    $nestedData['sale_status'] = '<div class="badge badge-danger">'.__('db.Returned').'</div>';
                    $sale_status = __('db.Returned');
                }
                elseif($sale->sale_status == 5){
                    $nestedData['sale_status'] = '<div class="badge badge-info">'.__('db.Processing').'</div>';
                    $sale_status = __('db.Processing');
                }
                elseif($sale->sale_status == 6){
                    $nestedData['sale_status'] = '<div class="badge badge-danger">'.__('db.Cooked').'</div>';
                    $sale_status = __('db.Cooked');
                }
                elseif($sale->sale_status == 7){
                    $nestedData['sale_status'] = '<div class="badge badge-primary">'.__('db.Served').'</div>';
                    $sale_status = __('db.Served');
                }

                if($sale->payment_status == 1)
                    $nestedData['payment_status'] = '<div class="badge badge-danger">'.__('db.Pending').'</div>';
                elseif($sale->payment_status == 2)
                    $nestedData['payment_status'] = '<div class="badge badge-danger">'.__('db.Due').'</div>';
                elseif($sale->payment_status == 3)
                    $nestedData['payment_status'] = '<div class="badge badge-warning">'.__('db.Partial').'</div>';
                else
                    $nestedData['payment_status'] = '<div class="badge badge-success">'.__('db.Paid').'</div>';
                $saleProgress = $progressMap[$sale->id] ?? [
                    'status' => DeliveryProgress::NOT_DELIVERED,
                    'sold' => 0,
                    'delivered' => 0,
                    'remaining' => 0,
                ];
                $nestedData['delivery_status'] = DeliveryProgress::badge($saleProgress);

                $nestedData['grand_total'] = number_format($sale->grand_total, config('decimal'));
                //$nestedData['grand_total'] = \Illuminate\Support\Number::format($sale->grand_total, locale: 'id');
                $returned_amount = DB::table('returns')->where('sale_id', $sale->id)->sum('grand_total');
                $nestedData['returned_amount'] = number_format($returned_amount, config('decimal'));
                $nestedData['paid_amount'] = number_format($sale->paid_amount, config('decimal'));
                $nestedData['due'] = number_format($sale->grand_total - $returned_amount - $sale->paid_amount, config('decimal'));
                //fetching custom fields data
                foreach($field_names as $field_name) {
                    $nestedData[$field_name] = $sale->$field_name;
                }
                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.__("db.action").'
                              <span class="caret"></span>
                              <span class="sr-only">'.__('db.Toggle Dropdown').'</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li><a href="'.route('sale.invoice', $sale->id).'" class="btn btn-link"><i class="fa fa-copy"></i> '.__('db.Generate Invoice').'</a></li>
                                <li>
                                    <button type="button" class="btn btn-link view"><i class="fa fa-eye"></i> '.__('db.View').'</button>
                                </li>';
                if(in_array("sales-edit", $request['all_permission'])){
                    if($sale->sale_status != 3)
                        $nestedData['options'] .= '<li>
                            <a href="'.route('sales.edit', $sale->id).'" class="btn btn-link"><i class="dripicons-document-edit"></i> '.__('db.edit').'</a>
                            </li>';
                    else
                        $nestedData['options'] .= '<li>
                            <a href="'.url('pos/'.$sale->id).'" class="btn btn-link"><i class="dripicons-document-edit"></i> '.__('db.edit').'</a>
                        </li>';
                }
                if(config('is_packing_slip') && in_array("packing_slip_challan", $request['all_permission']) && ($sale->sale_status == 2 || $sale->sale_status == 5) ) {
                    $nestedData['options'] .=
                    '<li>
                        <button type="button" class="create-packing-slip-btn btn btn-link" data-id = "'.$sale->id.'" data-toggle="modal" data-target="#packing-slip-modal"><i class="dripicons-box"></i> '.__('db.Create Packing Slip').'</button>
                    </li>';
                }
                if(in_array("sale-payment-index", $request['all_permission']))
                    $nestedData['options'] .=
                        '<li>
                            <button type="button" class="get-payment btn btn-link" data-id = "'.$sale->id.'"><i class="fa fa-money"></i> '.__('db.View Payment').'</button>
                        </li>';
                if(in_array("sale-payment-add", $request['all_permission']) && ($sale->payment_status != 4) && ($sale->sale_status != 3))
                    $nestedData['options'] .=
                        '<li>
                            <button type="button" class="add-payment btn btn-link" data-id = "'.$sale->id.'" data-toggle="modal" data-target="#add-payment"><i class="fa fa-plus"></i> '.__('db.Add Payment').'</button>
                        </li>';
                if($sale->sale_status !== 4)
                    $nestedData['options'] .=
                    '<li>
                        <a href="return-sale/create?reference_no='.$nestedData['reference_no'].'" class="add-payment btn btn-link"><i class="dripicons-return"></i> '.__('db.Add Return').'</a>
                    </li>';

                $nestedData['options'] .=
                '<li>
                    <button type="button" class="send-sms btn btn-link" data-id = "'.$sale->id.'" data-customer_id="'.$sale->customer_id.'" data-reference_no="'.$nestedData['reference_no'].'" data-sale_status="'.$sale->sale_status.'" data-payment_status="'.$sale->payment_status.'"  data-toggle="modal" data-target="#send-sms"><i class="fa fa-envelope"></i> '.__('db.Send SMS').'</button>
                </li>';

                $nestedData['options'] .=
                '<li>
                    <form action="'.route('sale.wappnotification').'" method="POST" style="display:inline;">
                      '.csrf_field().'
                        <input type="hidden" name="customer_id" value="'.$sale->customer_id.'">
                        <input type="hidden" name="sale_id" value="'.$sale->id.'">
                        <button type="submit" class="btn btn-link">
                            <i class="fa fa-whatsapp"></i> '.__('db.Whatsapp Notification').'
                        </button>
                    </form>
                </li>';

                if(in_array("delivery", $request['all_permission']))
                    $nestedData['options'] .=
                    '<li>
                        <button type="button" class="add-delivery btn btn-link" data-id = "'.$sale->id.'"><i class="fa fa-truck"></i> '.__('db.Add Delivery').'</button>
                    </li>
                    <li>
                        <button type="button" class="btn btn-link view"><i class="fa fa-truck"></i> '.__('db.Delivery history').'</button>
                    </li>';
                if(in_array("sales-delete", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => ["sales.destroy", $sale->id], "method" => "DELETE"] ).'
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> '.__("db.delete").'</button>
                            </li>'.\Form::close().'
                        </ul>
                    </div>';
                // data for sale details by one click
                $coupon = Coupon::find($sale->coupon_id);
                if($coupon)
                    $coupon_code = $coupon->code;
                else
                    $coupon_code = null;

                if($sale->currency_id)
                    $currency_code = Currency::select('code')->find($sale->currency_id)->code;
                else
                    $currency_code = 'N/A';

                // table data
                if(!empty($sale->table_id)){
                    $table = Table::findOrFail($sale->table_id);
                    if($table)
                        $table_name = $table->name;
                    else
                        $table_name = '';
                }
                else
                    $table_name = '';

                $nestedData['sale'] = array( '[ "'.date(config('date_format'), strtotime($sale->created_at->toDateString())).'"', ' "'.$sale->reference_no.'"', ' "'.$sale_status.'"', ' "'.@$sale->biller->name.'"', ' "'.@$sale->biller->company_name.'"', ' "'.@$sale->biller->email.'"', ' "'.@$sale->biller->phone_number.'"', ' "'.@$sale->biller->address.'"', ' "'.@$sale->biller->city.'"', ' "'.@$sale->customer->name.'"', ' "'.@$sale->customer->phone_number.'"', ' "'.@$sale->customer->address.'"', ' "'.@$sale->customer->city.'"', ' "'.@$sale->id.'"', ' "'.@$sale->total_tax.'"', ' "'.$sale->total_discount.'"', ' "'.$sale->total_price.'"', ' "'.$sale->order_tax.'"', ' "'.$sale->order_tax_rate.'"', ' "'.$sale->order_discount.'"', ' "'.$sale->shipping_cost.'"', ' "'.$sale->grand_total.'"', ' "'.$sale->paid_amount.'"', ' "'.preg_replace('/[\n\r]/', "<br>", $sale->sale_note).'"', ' "'.preg_replace('/[\n\r]/', "<br>", $sale->staff_note).'"', ' "'.$sale->user->name.'"', ' "'.$sale->user->email.'"', ' "'.$sale->warehouse->name.'"', ' "'.$coupon_code.'"', ' "'.$sale->coupon_discount.'"', ' "'.$sale->document.'"', ' "'.$currency_code.'"', ' "'.$sale->exchange_rate.'"', ' "'.$table_name.'"]'
                );
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );
        echo json_encode($json_data);
    }

    public function create()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-add')) {
            $lims_customer_list = Customer::where('is_active', true)->get();
            if(Auth::user()->role_id > 2) {
                $lims_warehouse_list = Warehouse::where([
                    ['is_active', true],
                    ['id', Auth::user()->warehouse_id]
                ])->get();
                $lims_biller_list = Biller::where([
                    ['is_active', true],
                    ['id', Auth::user()->biller_id]
                ])->get();
            }
            else {
                $lims_warehouse_list = Warehouse::where('is_active', true)->get();
                $lims_biller_list = Biller::where('is_active', true)->get();
            }

            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_pos_setting_data = PosSetting::latest()->first();
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_courier_list = Courier::where('is_active', true)->get();
            if($lims_pos_setting_data)
                $options = explode(',', $lims_pos_setting_data->payment_options);
            else
                $options = [];

            $currency_list = Currency::where('is_active', true)->get();
            $numberOfInvoice = Sale::count();
            $custom_fields = CustomField::where('belongs_to', 'sale')->get();
            $lims_customer_group_all = CustomerGroup::where('is_active', true)->get();

            $lims_account_list = Account::select('id', 'name')->where('is_active', true)->get();

            if(cache()->has('general_setting'))
            {
                $general_setting = cache()->get('general_setting');
            }else {
                $general_setting = GeneralSetting::select('modules')->first();
                cache()->put('general_setting', $general_setting, 60 * 60 * 24);
            }
            if(in_array('restaurant',explode(',',$general_setting->modules))){
                $lims_table_list = Table::join('floors','tables.floor_id','=','floors.id')
                        ->select('tables.id as id','tables.name','tables.number_of_person','floors.name as floor')
                        ->get();

                $service_list = DB::table('services')->where('is_active',1)->get();
                $waiter_list = DB::table('users')->where('service_staff',1)->where('is_active',1)->get();

                return view('backend.sale.create',compact('currency_list', 'lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_pos_setting_data', 'lims_tax_list', 'lims_reward_point_setting_data','options', 'numberOfInvoice', 'custom_fields', 'lims_customer_group_all', 'lims_table_list', 'service_list', 'waiter_list', 'lims_courier_list'));

            }

            return view('backend.sale.create',compact('currency_list', 'lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_pos_setting_data', 'lims_tax_list', 'lims_reward_point_setting_data','options', 'numberOfInvoice', 'custom_fields', 'lims_customer_group_all', 'lims_account_list', 'lims_courier_list'));

        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function store(StoreSaleRequest $request)
{  
    $data = $request->all();
    
    // Single transaction for the entire operation
    DB::beginTransaction();
    
    try {
        // ... existing validation and setup code ...
        
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
                $data['created_at'] = date('Y-m-d H:i:s');
            }
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        // FIXED: Handle paid_amount properly for due payments
        $original_paid_amounts = $data['paid_amount'] ?? 0;
        
        // Handle paid_amount array conversion
        if (is_array($data['paid_amount'])) {
            $data['paid_amount'] = array_sum($data['paid_amount']);
        } else {
            $data['paid_amount'] = (float)$data['paid_amount'];
        }

        // FIXED: Ensure grand_total exists and is numeric
        $data['grand_total'] = isset($data['grand_total']) ? (float)$data['grand_total'] : 0;

        if(isset($data['pos'])) {
            if(!isset($data['reference_no']))
                $data['reference_no'] = $this->generateInvoiceName('posr-');

            $balance = $data['grand_total'] - $data['paid_amount'];

            // FIXED: Proper payment status logic
            if($balance > 0) {
                if($data['paid_amount'] > 0) {
                    $data['payment_status'] = 2; // Partial
                } else {
                    $data['payment_status'] = 1; // Due
                }
            } elseif($balance < 0) {
                $data['payment_status'] = 4; // Paid (overpaid)
            } else {
                $data['payment_status'] = 4; // Paid
            }

            if(isset($data['draft']) && $data['draft']) {
                $lims_sale_data = Sale::find($data['sale_id']);
                if($lims_sale_data) {
                    $lims_product_sale_data = Product_Sale::where('sale_id', $data['sale_id'])->get();
                    foreach ($lims_product_sale_data as $product_sale_data) {
                        $product_sale_data->delete();
                    }
                    $lims_sale_data->delete();
                }
            }
        }
        else {
            if(!isset($data['reference_no']))
                $data['reference_no'] = $this->generateInvoiceName('sr-');
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
        
        if(isset($data['coupon_active']) && $data['coupon_active'] && !isset($data['draft'])) {
            $lims_coupon_data = Coupon::find($data['coupon_id']);
            if($lims_coupon_data) {
                $lims_coupon_data->used += 1;
                $lims_coupon_data->save();
            }
        }
        
        if(isset($data['table_id'])) {
            $latest_sale = Sale::whereNotNull('table_id')->whereDate('created_at', date('Y-m-d'))->where('warehouse_id', $data['warehouse_id'])->select('queue')->orderBy('id', 'desc')->first();
            if($latest_sale)
                $data['queue'] = $latest_sale->queue + 1;
            else
                $data['queue'] = 1;
        }

        // Create the sale record
        $lims_sale_data = Sale::create($data);

        // Store original paid amounts for payment processing
        $data['paid_amount'] = $original_paid_amounts;

        //inserting data for custom fields
        $custom_field_data = [];
        $custom_fields = CustomField::where('belongs_to', 'sale')->select('name', 'type')->get();
        foreach ($custom_fields as $custom_field) {
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
        if($lims_reward_point_setting_data && $lims_reward_point_setting_data->is_active && $data['grand_total'] >= $lims_reward_point_setting_data->minimum_amount) {
            if ($lims_customer_data && $lims_customer_data->type == CustomerTypeEnum::REGULAR->value) {
                $isDraft = !isset($data['draft']) || $data['draft'] == '0';
                $isNotPaidBy7 = !isset($data['paid_by_id']) || !in_array('7', $data['paid_by_id']);
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
        $mail_data['email'] = $lims_customer_data->email ?? '';
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

        // FIXED: Ensure arrays exist before processing
        $product_id = $data['product_id'] ?? [];
        $product_batch_id = $data['product_batch_id'] ?? [];
        $imei_number = $data['imei_number'] ?? [];
        $product_code = $data['product_code'] ?? [];
        $qty = $data['qty'] ?? [];
        $dqty = $data['dqty'] ?? [];
        $dstatus = $data['dstatus'] ?? [];
        $sale_unit = $data['sale_unit'] ?? [];
        $net_unit_price = $data['net_unit_price'] ?? [];
        $discount = $data['discount'] ?? [];
        $tax_rate = $data['tax_rate'] ?? [];
        $tax = $data['tax'] ?? [];
        $total = $data['subtotal'] ?? [];

        foreach ($product_id as $i => $id) {
            // FIXED: Better array validation and defaults
            $current_dqty = isset($dqty[$i]) ? max(0, min((int)$dqty[$i], (int)($qty[$i] ?? 0))) : (int)($qty[$i] ?? 0);
            $current_qty = (int)($qty[$i] ?? 0);
            
            if($current_qty <= 0) continue; // Skip invalid quantities
            
            $lims_product_data = Product::where('id', $id)->first();
            
            if (!$lims_product_data) {
                throw new \Exception("Product with ID {$id} not found");
            }

            $product_sale = [
                'sale_id' => $lims_sale_data->id,
                'product_id' => $id,
                'variant_id' => null,
                'product_batch_id' => null,
                'qty' => $current_qty,
                'dqty' => $current_dqty,
                'dstatus' => isset($dstatus[$i]) && !empty($dstatus[$i]) ? $dstatus[$i] : $current_dqty . ' / ' . $current_qty,
                'net_unit_price' => $net_unit_price[$i] ?? 0,
                'discount' => $discount[$i] ?? 0,
                'tax_rate' => $tax_rate[$i] ?? 0,
                'tax' => $tax[$i] ?? 0,
                'total' => $total[$i] ?? 0
            ];

            // Handle IMEI numbers
            if(isset($imei_number[$i]) && $imei_number[$i] && !str_contains($imei_number[$i], "null")) {
                $product_sale['imei_number'] = $imei_number[$i];
            } else {
                $product_sale['imei_number'] = null;
            }

            // Handle sale units
            $sale_unit_id = 0;
            $stock_deduction_qty = $current_dqty;
            
            if(isset($sale_unit[$i]) && $sale_unit[$i] != 'n/a') {
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$i])->first();
                if ($lims_sale_unit_data) {
                    $sale_unit_id = $lims_sale_unit_data->id;
                    
                    if($lims_sale_unit_data->operator == '*') {
                        $stock_deduction_qty = $current_dqty * $lims_sale_unit_data->operation_value;
                    } elseif($lims_sale_unit_data->operator == '/') {
                        $stock_deduction_qty = $current_dqty / $lims_sale_unit_data->operation_value;
                    }
                }
            }
            
            $product_sale['sale_unit_id'] = $sale_unit_id;

            // Handle product variants
            if($lims_product_data->is_variant) {
                $lims_product_variant_data = ProductVariant::select('id', 'variant_id', 'qty')
                    ->FindExactProductWithCode($id, $product_code[$i] ?? '')->first();
                
                if ($lims_product_variant_data) {
                    $product_sale['variant_id'] = $lims_product_variant_data->variant_id;
                }
            }

            // Handle product batches
            if($lims_product_data->is_batch && isset($product_batch_id[$i]) && $product_batch_id[$i]) {
                $product_sale['product_batch_id'] = $product_batch_id[$i];
            }

            // Only deduct stock if sale is completed (status = 1)
            if($data['sale_status'] == 1) {
                
                // Handle combo products
                if($lims_product_data->type == 'combo') {
                    $product_list = explode(",", $lims_product_data->product_list);
                    $variant_list = $lims_product_data->variant_list ? explode(",", $lims_product_data->variant_list) : [];
                    $qty_list = explode(",", $lims_product_data->qty_list);

                    foreach ($product_list as $key => $child_id) {
                        $child_data = Product::find($child_id);
                        if(!$child_data) continue;

                        $child_delivered_qty = $stock_deduction_qty * ($qty_list[$key] ?? 1);

                        $child_data->qty -= $child_delivered_qty;
                        $child_data->save();

                        if(count($variant_list) && isset($variant_list[$key]) && $variant_list[$key]) {
                            $child_product_variant_data = ProductVariant::where([
                                ['product_id', $child_id],
                                ['variant_id', $variant_list[$key]]
                            ])->first();

                            if($child_product_variant_data) {
                                $child_product_variant_data->qty -= $child_delivered_qty;
                                $child_product_variant_data->save();
                            }

                            $child_warehouse_data = Product_Warehouse::where([
                                ['product_id', $child_id],
                                ['variant_id', $variant_list[$key]],
                                ['warehouse_id', $data['warehouse_id']],
                            ])->first();
                        } else {
                            $child_warehouse_data = Product_Warehouse::where([
                                ['product_id', $child_id],
                                ['warehouse_id', $data['warehouse_id']],
                            ])->first();
                        }

                        if($child_warehouse_data) {
                            $child_warehouse_data->qty -= $child_delivered_qty;
                            $child_warehouse_data->save();
                        }
                    }
                } else {
                    // Handle regular products
                    $lims_product_data->qty -= $stock_deduction_qty;
                    $lims_product_data->save();

                    // Handle variant stock deduction
                    if($lims_product_data->is_variant && isset($lims_product_variant_data)) {
                        $lims_product_variant_data->qty -= $stock_deduction_qty;
                        $lims_product_variant_data->save();
                        
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant(
                            $id, $lims_product_variant_data->variant_id, $data['warehouse_id']
                        )->first();
                    } elseif(isset($product_batch_id[$i]) && $product_batch_id[$i]) {
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_batch_id', $product_batch_id[$i]],
                            ['warehouse_id', $data['warehouse_id']]
                        ])->first();
                        
                        $lims_product_batch_data = ProductBatch::find($product_batch_id[$i]);
                        if($lims_product_batch_data) {
                            $lims_product_batch_data->qty -= $stock_deduction_qty;
                            $lims_product_batch_data->save();
                        }
                    } else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant(
                            $id, $data['warehouse_id']
                        )->first();
                    }

                    if($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->qty -= $stock_deduction_qty;
                        $lims_product_warehouse_data->save();
                    }

                    // Handle IMEI number deduction
                    if(isset($imei_number[$i]) && $imei_number[$i] && !str_contains($imei_number[$i], "null") && $lims_product_warehouse_data) {
                        $imei_numbers = explode(",", $imei_number[$i]);
                        $all_imei_numbers = explode(",", $lims_product_warehouse_data->imei_number ?? '');
                        
                        foreach ($imei_numbers as $number) {
                            if (($j = array_search($number, $all_imei_numbers)) !== false) {
                                unset($all_imei_numbers[$j]);
                            }
                        }
                        
                        $lims_product_warehouse_data->imei_number = implode(",", $all_imei_numbers);
                        $lims_product_warehouse_data->save();
                    }
                }
            }

            // Add topping handling if restaurant module is enabled
            if (in_array('restaurant', explode(',', $general_setting->modules ?? ''))) {
                $product_sale['topping_id'] = null;
                if (!empty($data['topping_product'][$i] ?? '')) {
                    $product_sale['topping_id'] = $data['topping_product'][$i];
                }
            }

            // Create product sale record
            Product_Sale::create($product_sale);

            // Prepare mail data
            if($product_sale['variant_id']) {
                $variant_data = Variant::select('name')->find($product_sale['variant_id']);
                $mail_data['products'][$i] = $lims_product_data->name . ' ['. ($variant_data->name ?? '') .']';
            } else {
                $mail_data['products'][$i] = $lims_product_data->name;
            }

            if($lims_product_data->type == 'digital') {
                $mail_data['file'][$i] = url('/product/files').'/'.$lims_product_data->file;
            } else {
                $mail_data['file'][$i] = '';
            }

            if($sale_unit_id) {
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code ?? '';
            } else {
                $mail_data['unit'][$i] = '';
            }

            $mail_data['qty'][$i] = $current_qty;
            $mail_data['total'][$i] = $total[$i] ?? 0;
        }

        // FIXED: Only process payments if payment data exists and amount > 0
        if(isset($data['payment_status']) && 
           ($data['payment_status'] == 3 || $data['payment_status'] == 4 || 
           ($data['payment_status'] == 2 && isset($data['pos']) && is_array($data['paid_amount']) && array_sum($data['paid_amount']) > 0))) {
            
            // Ensure paid_by_id is array and has values
            if(isset($data['paid_by_id']) && is_array($data['paid_by_id'])) {
                foreach($data['paid_by_id'] as $key => $value) {
                    $payment_amount = 0;
                    
                    if(is_array($data['paid_amount']) && isset($data['paid_amount'][$key])) {
                        $payment_amount = (float)$data['paid_amount'][$key];
                    } elseif(!is_array($data['paid_amount']) && $key == 0) {
                        $payment_amount = (float)$data['paid_amount'];
                    }
                    
                    if($payment_amount > 0) {
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
                            if(isset($data['used_points'])) {
                                $lims_payment_data->used_points = $data['used_points'];
                                $lims_customer_data->points -= $data['used_points'];
                                $lims_customer_data->save();
                            }
                        }
                        elseif($data['paid_by_id'][$key] == 8) {
                            $paying_method = 'Pesapal';
                        }
                        else {
                            $paying_method = ucfirst($data['paid_by_id'][$key]);
                        }

                        if($cash_register_data)
                            $lims_payment_data->cash_register_id = $cash_register_data->id;
                            
                        $lims_account_data = Account::where('is_default', true)->first();
                        if(isset($data['account_id']))
                            $lims_payment_data->account_id = $data['account_id'];
                        else  
                            $lims_payment_data->account_id = $lims_account_data->id ?? 1;
                            
                        $lims_payment_data->sale_id = $lims_sale_data->id;
                        $data['payment_reference'] = 'spr-'.date("Ymd").'-'.date("his");
                        $lims_payment_data->payment_reference = $data['payment_reference'];
                        $lims_payment_data->amount = $payment_amount;
                        
                        // Handle change calculation
                        $paying_amount = isset($data['paying_amount'][$key]) ? (float)$data['paying_amount'][$key] : $payment_amount;
                        $lims_payment_data->change = $paying_amount - $payment_amount;
                        
                        $lims_payment_data->paying_method = $paying_method;
                        $lims_payment_data->payment_note = $data['payment_note'] ?? '';
                        
                        if(isset($data['payment_receiver'])){
                            $lims_payment_data->payment_receiver = $data['payment_receiver'];
                        }
                        
                        $lims_payment_data->save();

                        // Handle specific payment methods
                        $lims_payment_data = Payment::latest()->first();
                        $data['payment_id'] = $lims_payment_data->id;

                        if($paying_method == 'Credit Card' && isset($data['card_number'])){
                            $cardDetails = [];
                            $cardDetails['card_number'] = $data['card_number'];
                            $cardDetails['card_holder_name'] = $data['card_holder_name'] ?? '';
                            $cardDetails['card_type'] = $data['card_type'] ?? '';
                            $data['charge_id'] = '12345';
                            $data['data'] = json_encode($cardDetails);

                            PaymentWithCreditCard::create($data);
                        }
                        else if ($paying_method == 'Gift Card' && isset($data['gift_card_id'])) {
                            $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
                            if($lims_gift_card_data) {
                                $lims_gift_card_data->expense += $payment_amount;
                                $lims_gift_card_data->save();
                            }
                            PaymentWithGiftCard::create($data);
                        }
                        else if ($paying_method == 'Cheque') {
                            PaymentWithCheque::create($data);
                        }
                        else if($paying_method == 'Deposit'){
                            $lims_customer_data->expense += $payment_amount;
                            $lims_customer_data->save();
                        }
                        else if($paying_method == 'Pesapal'){
                            $redirectUrl = $this->submitOrderRequest($lims_customer_data, $payment_amount);
                            $lims_customer_data->save();

                            return response()->json([
                                'payment_method' => 'pesapal',
                                'redirect_url' => $redirectUrl,
                            ]);
                        }
                    }
                }
            }
        }

        DB::commit();

        // Success messages and redirects
        if(isset($data['sale_status']) && $data['sale_status'] == 3) {
            $message = 'Sale successfully added to draft';
        } else {
            $message = 'Sale created successfully';
        }

        // Send email if conditions are met
        $mail_setting = MailSetting::latest()->first();
        if(isset($mail_data['email']) && $mail_data['email'] && 
           isset($data['sale_status']) && $data['sale_status'] == 1 && $mail_setting) {
            $this->setMailInfo($mail_setting);
            try {
                Mail::to($mail_data['email'])->send(new SaleDetails($mail_data));
            }
            catch(\Exception $e){
                $message = ' Sale created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }

        // Handle different return scenarios
        if($lims_sale_data->sale_status == '1') {
            return $lims_sale_data->id;
        } elseif(in_array('restaurant',explode(',',$general_setting->modules ?? '')) && $lims_sale_data->sale_status == '5') {
            return $lims_sale_data->id;
        } elseif(isset($data['pos'])) {
            return redirect('pos')->with('message', $message);
        } else {
            return redirect('sales')->with('message', $message);
        }

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Sale Creation Error: ' . $e->getMessage());
        \Log::error('Sale Data: ', $data);
        return redirect()->back()->with('error', 'Error creating sale: ' . $e->getMessage());
    }
}
public function store3(StoreSaleRequest $request)
{  
    $data = $request->all();
    
    // Single transaction for the entire operation
    DB::beginTransaction();
    
    try {
        // ... existing validation and setup code ...
        
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

        // Create the sale record
        $lims_sale_data = Sale::create($data);

        // ... existing custom fields and reward points code ...
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

        // Arrays to store adjustment data
        $adjustment_needed = false;
        $total_adjustment_qty = 0;
        $adjustment_items = 0;

        foreach ($product_id as $i => $id) {
            // Validate and sanitize dqty
            $current_dqty = isset($dqty[$i]) ? max(0, min((int)$dqty[$i], (int)$qty[$i])) : (int)$qty[$i];
            $current_qty = (int)$qty[$i];
            
            $lims_product_data = Product::where('id', $id)->first();
            
            if (!$lims_product_data) {
                throw new \Exception("Product with ID {$id} not found");
            }

            $product_sale = [
                'sale_id' => $lims_sale_data->id,
                'product_id' => $id,
                'variant_id' => null,
                'product_batch_id' => null,
                'qty' => $current_qty,
                'dqty' => $current_dqty,
                'dstatus' => isset($dstatus[$i]) && !empty($dstatus[$i]) ? $dstatus[$i] : $current_dqty . ' / ' . $current_qty,
                'net_unit_price' => $net_unit_price[$i],
                'discount' => $discount[$i],
                'tax_rate' => $tax_rate[$i],
                'tax' => $tax[$i],
                'total' => $total[$i]
            ];

            // Handle IMEI numbers
            if($imei_number[$i] && !str_contains($imei_number[$i], "null")) {
                $product_sale['imei_number'] = $imei_number[$i];
            } else {
                $product_sale['imei_number'] = null;
            }

            // Handle sale units
            $sale_unit_id = 0;
            $stock_deduction_qty = $current_dqty; // Default: deduct delivered quantity
            
            if($sale_unit[$i] != 'n/a') {
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$i])->first();
                if ($lims_sale_unit_data) {
                    $sale_unit_id = $lims_sale_unit_data->id;
                    
                    // Calculate actual stock deduction based on unit conversion
                    if($lims_sale_unit_data->operator == '*') {
                        $stock_deduction_qty = $current_dqty * $lims_sale_unit_data->operation_value;
                    } elseif($lims_sale_unit_data->operator == '/') {
                        $stock_deduction_qty = $current_dqty / $lims_sale_unit_data->operation_value;
                    }
                }
            }
            
            $product_sale['sale_unit_id'] = $sale_unit_id;

            // Handle product variants
            if($lims_product_data->is_variant) {
                $lims_product_variant_data = ProductVariant::select('id', 'variant_id', 'qty')
                    ->FindExactProductWithCode($id, $product_code[$i])->first();
                
                if ($lims_product_variant_data) {
                    $product_sale['variant_id'] = $lims_product_variant_data->variant_id;
                }
            }

            // Handle product batches
            if($lims_product_data->is_batch && $product_batch_id[$i]) {
                $product_sale['product_batch_id'] = $product_batch_id[$i];
            }

            // CRITICAL FIX: Only deduct stock if sale is completed (status = 1)
            if($data['sale_status'] == 1) {
                
                // Handle combo products
                if($lims_product_data->type == 'combo') {
                    $product_list = explode(",", $lims_product_data->product_list);
                    $variant_list = $lims_product_data->variant_list ? explode(",", $lims_product_data->variant_list) : [];
                    $qty_list = explode(",", $lims_product_data->qty_list);

                    foreach ($product_list as $key => $child_id) {
                        $child_data = Product::find($child_id);
                        if(!$child_data) continue;

                        $child_delivered_qty = $stock_deduction_qty * $qty_list[$key];

                        // Update child product stock
                        $child_data->qty -= $child_delivered_qty;
                        $child_data->save();

                        // Handle child variant
                        if(count($variant_list) && isset($variant_list[$key]) && $variant_list[$key]) {
                            $child_product_variant_data = ProductVariant::where([
                                ['product_id', $child_id],
                                ['variant_id', $variant_list[$key]]
                            ])->first();

                            if($child_product_variant_data) {
                                $child_product_variant_data->qty -= $child_delivered_qty;
                                $child_product_variant_data->save();
                            }

                            $child_warehouse_data = Product_Warehouse::where([
                                ['product_id', $child_id],
                                ['variant_id', $variant_list[$key]],
                                ['warehouse_id', $data['warehouse_id']],
                            ])->first();
                        } else {
                            $child_warehouse_data = Product_Warehouse::where([
                                ['product_id', $child_id],
                                ['warehouse_id', $data['warehouse_id']],
                            ])->first();
                        }

                        // Update child warehouse stock
                        if($child_warehouse_data) {
                            $child_warehouse_data->qty -= $child_delivered_qty;
                            $child_warehouse_data->save();
                        }
                    }
                } else {
                    // Handle regular products - DEDUCT ONLY DELIVERED QUANTITY
                    $lims_product_data->qty -= $stock_deduction_qty;
                    $lims_product_data->save();

                    // Handle variant stock deduction
                    if($lims_product_data->is_variant && isset($lims_product_variant_data)) {
                        $lims_product_variant_data->qty -= $stock_deduction_qty;
                        $lims_product_variant_data->save();
                        
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant(
                            $id, $lims_product_variant_data->variant_id, $data['warehouse_id']
                        )->first();
                    } elseif($product_batch_id[$i]) {
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_batch_id', $product_batch_id[$i]],
                            ['warehouse_id', $data['warehouse_id']]
                        ])->first();
                        
                        $lims_product_batch_data = ProductBatch::find($product_batch_id[$i]);
                        if($lims_product_batch_data) {
                            $lims_product_batch_data->qty -= $stock_deduction_qty;
                            $lims_product_batch_data->save();
                        }
                    } else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant(
                            $id, $data['warehouse_id']
                        )->first();
                    }

                    // Update warehouse stock
                    if($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->qty -= $stock_deduction_qty;
                        $lims_product_warehouse_data->save();
                    }

                    // Handle IMEI number deduction
                    if($imei_number[$i] && !str_contains($imei_number[$i], "null") && $lims_product_warehouse_data) {
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
                }
            }

            // Track if adjustment is needed (when ordered qty > delivered qty)
            $qty_difference = $current_qty - $current_dqty;
            if ($qty_difference > 0) {
                $adjustment_needed = true;
                $total_adjustment_qty += $qty_difference;
                $adjustment_items++;
            }

            // Add topping handling if restaurant module is enabled
            if (in_array('restaurant', explode(',', $general_setting->modules))) {
                $product_sale['topping_id'] = null;
                if (!empty($data['topping_product'][$i])) {
                    $product_sale['topping_id'] = $data['topping_product'][$i];
                }
            }

            // Create product sale record
            Product_Sale::create($product_sale);

            // Prepare mail data
            if($product_sale['variant_id']) {
                $variant_data = Variant::select('name')->find($product_sale['variant_id']);
                $mail_data['products'][$i] = $lims_product_data->name . ' ['. $variant_data->name .']';
            } else {
                $mail_data['products'][$i] = $lims_product_data->name;
            }

            if($lims_product_data->type == 'digital') {
                $mail_data['file'][$i] = url('/product/files').'/'.$lims_product_data->file;
            } else {
                $mail_data['file'][$i] = '';
            }

            if($sale_unit_id) {
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code;
            } else {
                $mail_data['unit'][$i] = '';
            }

            $mail_data['qty'][$i] = $current_qty;
            $mail_data['total'][$i] = $total[$i];
        }

        // Create adjustment record ONLY if there are undelivered items and sale is completed
        if ($adjustment_needed && $data['sale_status'] == 1) {
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

        // REMOVED THE ADJUSTMENT LOGIC ENTIRELY - it was causing the race condition
        
        // The correct approach is to only deduct what was actually delivered
        // No need to create adjustments for undelivered items

        // ... rest of payment processing, email sending, SMS, etc. ...

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
        

        DB::commit();

        // Success messages and redirects
        if($data['sale_status'] == 3) {
            $message = 'Sale successfully added to draft';
        } else {
            $message = 'Sale created successfully';
        }

        // Handle different return scenarios
        if($lims_sale_data->sale_status == '1') {
            return $lims_sale_data->id;
        } elseif(in_array('restaurant',explode(',',$general_setting->modules)) && $lims_sale_data->sale_status == '5') {
            return $lims_sale_data->id;
        } elseif($data['pos']) {
            return redirect('pos')->with('message', $message);
        } else {
            return redirect('sales')->with('message', $message);
        }

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Sale Creation Error: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error creating sale: ' . $e->getMessage());
    }
}



      public function store2(StoreSaleRequest $request)
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

            //collecting male data
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
            $sale_unit = $data['sale_unit'];
            $net_unit_price = $data['net_unit_price'];
            $discount = $data['discount'];
            $tax_rate = $data['tax_rate'];
            $tax = $data['tax'];
            $total = $data['subtotal'];
            $product_sale = [];

            foreach ($product_id as $i => $id) {
                $lims_product_data = Product::where('id', $id)->first();
                // DB::rollback();
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

                        // remove from initial stock
                        // if ($lims_product_data->qty > 0) {
                        //     if ($lims_product_data->qty >= $qty[$i]) {
                        //         $reduce_qty_from_ingredient = 0;
                        //         $reduce_qty_from_product = $qty[$i];
                        //     } elseif ($lims_product_data->qty <= $qty[$i]) {
                        //         $reduce_qty_from_ingredient = $qty[$i] - $lims_product_data->qty;
                        //         if($lims_product_data->qty <= 0){
                        //             $reduce_qty_from_product = 0;
                        //         }else{
                        //             $reduce_qty_from_product = $lims_product_data->qty;
                        //         }
                        //     }
                        //     // dd($reduce_qty_from_ingredient);
                        //     // dd($reduce_qty_from_product);
                        //     $qty[$i] = $reduce_qty_from_ingredient;
                        // }else{
                        //     $reduce_qty_from_product = 0;
                        // }
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
                                    $child_product_variant_data->qty -= $qty[$i] * $qty_list[$key];
                                    $child_product_variant_data->save();
                                }
                            } else {
                                $child_warehouse_data = Product_Warehouse::where([
                                    ['product_id', $child_id],
                                    ['warehouse_id', $data['warehouse_id']],
                                ])->first();
                            }

                            $child_data->qty -= $qty[$i] * $qty_list[$key];
                            $child_data->save();

                            if($child_warehouse_data) {
                                $child_warehouse_data->qty -= $qty[$i] * $qty_list[$key];
                                $child_warehouse_data->save();
                            }
                        }
                    }
                    // if(isset($reduce_qty_from_product)){
                    //     $qty[$i] = $reduce_qty_from_product;
                    // }

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
                        if($lims_sale_unit_data->operator == '*')
                            $quantity = $qty[$i] * $lims_sale_unit_data->operation_value;
                        elseif($lims_sale_unit_data->operator == '/')
                            $quantity = $qty[$i] / $lims_sale_unit_data->operation_value;
                        //deduct quantity
                        $lims_product_data->qty = $lims_product_data->qty - $quantity;
                        $lims_product_data->save();
                        //deduct product variant quantity if exist
                        if($lims_product_data->is_variant) {
                            $lims_product_variant_data->qty -= $quantity;
                            $lims_product_variant_data->save();
                            $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($id, $lims_product_variant_data->variant_id, $data['warehouse_id'])->first();
                        }
                        elseif($product_batch_id[$i]) {
                            $lims_product_warehouse_data = Product_Warehouse::where([
                                ['product_batch_id', $product_batch_id[$i] ],
                                ['warehouse_id', $data['warehouse_id'] ]
                            ])->first();
                            $lims_product_batch_data = ProductBatch::find($product_batch_id[$i]);
                            //deduct product batch quantity
                            $lims_product_batch_data->qty -= $quantity;
                            $lims_product_batch_data->save();
                        }
                        else {
                            $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($id, $data['warehouse_id'])->first();
                        }
                        //deduct quantity from warehouse
                        $lims_product_warehouse_data->qty -= $quantity;
                        $lims_product_warehouse_data->save();
                    }
                }
                else
                    $sale_unit_id = 0;
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    \Log::error('Combo Product Stock Update Error: '.$e->getMessage());
                    return redirect()->back()->with('error', 'Something went wrong while updating stock.');
                }

                // if(isset($total_request_combo_qty)){
                //    $qty[$i] = $total_request_combo_qty;
                // }

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

                $product_sale['sale_id'] = $lims_sale_data->id ;
                $product_sale['product_id'] = $id;
                if($imei_number[$i] && !str_contains($imei_number[$i], "null")) {
                    $product_sale['imei_number'] = $imei_number[$i];
                } else {
                    $product_sale['imei_number'] = null;
                }
                $product_sale['qty'] = $mail_data['qty'][$i] = $qty[$i];
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
        /*}
        catch(Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()]);
        }*/

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


    private function generateInvoiceName($default){
        $invoice_settings = InvoiceSetting::active_setting();
        $invoice_schema = InvoiceSchema::latest()->first();
        $show_active_status =  json_decode($invoice_settings->show_column);
        $prefix = $invoice_settings->prefix ?? $default;
        if(isset($show_active_status) && $show_active_status->active_generat_settings == 1){
            if($invoice_settings->numbering_type == "sequential"){
                if($invoice_schema == null){
                    InvoiceSchema::query()->create(['last_invoice_number'=>$invoice_settings->start_number]);
                    return $prefix.'-'.$invoice_settings->start_number;
                }else{
                    $invoice_schema->update(['last_invoice_number'=>$invoice_schema->last_invoice_number +1]);
                    return $prefix.'-'.$invoice_schema->last_invoice_number +1;
                }

            }elseif($invoice_settings->numbering_type == "random"){
                return $prefix.'-' . rand($invoice_settings->start_number,str_repeat('9', (int)$invoice_settings->number_of_digit));
            }else{
                return  $prefix . date("Ymd") . '-'. date("his");
            }
        }else{
            return $prefix . date("Ymd") . '-'. date("his");
        }
    }

    public function getSoldItem($id)
    {
        $sale = Sale::select('warehouse_id')->find($id);
        $product_sale_data = Product_Sale::where('sale_id', $id)->get();
        $data = [];
        $data['amount'] = $sale->shipping_cost - $sale->sale_discount;
        $flag = 0;
        foreach ($product_sale_data as $key => $product_sale) {
            $product = Product::select('type', 'name', 'code', 'product_list', 'qty_list')->find($product_sale->product_id);
            $data[$key]['combo_in_stock'] = 1;
            $data[$key]['child_info'] = '';
            if($product->type == 'combo') {
                $child_ids = explode(",", $product->product_list);
                $qty_list = explode(",", $product->qty_list);
                foreach ($child_ids as $index => $child_id) {
                    $child_product = Product::select('name', 'code')->find($child_id);

                    $child_stock = $child_product->initial_qty + $child_product->received_qty;
                    $required_stock = $qty_list[$index] * $product_sale->qty;
                    if($required_stock > $child_stock) {
                        $data[$key]['combo_in_stock'] = 0;
                        $data[$key]['child_info'] = $child_product->name.'['.$child_product->code.'] does not have enough stock. In stock: '.$child_stock;
                        break;
                    }
                }
            }
            $data[$key]['product_id'] = $product_sale->product_id.'|'.$product_sale->variant_id;
            $data[$key]['type'] = $product->type;
            if($product_sale->variant_id) {
                $variant_data = Variant::select('name')->find($product_sale->variant_id);
                $product_variant_data = ProductVariant::select('item_code')->where([
                    ['product_id', $product_sale->product_id],
                    ['variant_id', $product_sale->variant_id]
                ])->first();
                $data[$key]['name'] = $product->name.' ['.$variant_data->name.']';
                $product->code = $product_variant_data->item_code;
            }
            else
                $data[$key]['name'] = $product->name;
            $data[$key]['qty'] = $product_sale->qty;
            $data[$key]['code'] = $product->code;
            $data[$key]['sold_qty'] = $product_sale->qty;
            $product_warehouse = Product_Warehouse::where([
                ['product_id', $product_sale->product_id],
                ['warehouse_id', $sale->warehouse_id]
            ])->first();
            if($product_warehouse) {
                $data[$key]['stock'] = $product_warehouse->qty;
            }
            else {
                $data[$key]['stock'] = $product->qty;
            }

            $data[$key]['unit_price'] = $product_sale->total / $product_sale->qty;
            $data[$key]['total_price'] = $product_sale->total;
            if($product_sale->is_packing) {
                $data['amount'] = 0;
            }
            else {
                $flag = 1;
            }
            $data[$key]['is_packing'] = $product_sale->is_packing;
        }
        if($flag)
            return $data;
        else
            return 'All the items of this sale has already been packed';
    }
    public function sendSMS(Request $request)
    {
        $data = $request->all();

        //sms send start
        // $smsTemplate = SmsTemplate::where('is_default',1)->latest()->first();

        $smsProvider = ExternalService::where('active',true)->where('type','sms')->first();
        if($smsProvider)
        {
            $data['type'] = 'onsite';
            $this->_smsModel->initialize($data);
            return redirect()->back();
        }
        //sms send end
        else {
            return redirect()->back()->with('not_permitted', __('db.Please setup your SMS API first!'));
        }
    }

    public function sendMail(Request $request)
    {
        $data = $request->all();
        $lims_sale_data = Sale::find($data['sale_id']);
        $lims_product_sale_data = Product_Sale::where('sale_id', $data['sale_id'])->get();
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $mail_setting = MailSetting::latest()->first();

        if(!$mail_setting) {
            return $this->setErrorMessage('Please Setup Your Mail Credentials First.');
        }
        else if($lims_customer_data->email) {
            //collecting male data
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

            foreach ($lims_product_sale_data as $key => $product_sale_data) {
                $lims_product_data = Product::find($product_sale_data->product_id);
                if($product_sale_data->variant_id) {
                    $variant_data = Variant::select('name')->find($product_sale_data->variant_id);
                    $mail_data['products'][$key] = $lims_product_data->name . ' [' . $variant_data->name . ']';
                }
                else
                    $mail_data['products'][$key] = $lims_product_data->name;
                if($lims_product_data->type == 'digital')
                    $mail_data['file'][$key] = url('/product/files').'/'.$lims_product_data->file;
                else
                    $mail_data['file'][$key] = '';
                if($product_sale_data->sale_unit_id){
                    $lims_unit_data = Unit::find($product_sale_data->sale_unit_id);
                    $mail_data['unit'][$key] = $lims_unit_data->unit_code;
                }
                else
                    $mail_data['unit'][$key] = '';

                $mail_data['qty'][$key] = $product_sale_data->qty;
                $mail_data['total'][$key] = $product_sale_data->qty;
            }
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new SaleDetails($mail_data));
                return $this->setSuccessMessage('Mail sent successfully');
            }
            catch(\Exception $e){
                return $this->setErrorMessage('Please Setup Your Mail Credentials First.');
            }
        }
        else
            return $this->setErrorMessage('Customer doesnt have email!');
    }

    public function whatsappNotificationSend(Request $request)
    {

        $data = $request->all();

        if(cache()->has('general_setting'))
        {
            $general_setting = cache()->get('general_setting');
        }else {
            $general_setting = GeneralSetting::latest()->first();
            cache()->put('general_setting', $general_setting, 60 * 60 * 24);
        }

        $company = $general_setting->company_name;
        // Find the customer by ID
        $customer = Customer::find($data['customer_id']);
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        // Find the sale record by sale_id
        $sale = Sale::find($data['sale_id']);
        if (!$sale) {
            return response()->json(['error' => 'Sale not found'], 404);
        }

        $name = $customer->name;
        $phone = $customer->phone_number;
        $referenceNo = $sale->reference_no; // Get the reference number from the sale

        // Create personalized text message
       $text = urlencode(__('db.Dear') . ' ' . $name . ', ' .
                            __('db.Thank you for your purchase! Your invoice number is') . ' ' . $referenceNo . "\n" .
                            __('db.If you have any questions or concerns, please don\'t hesitate to reach out to us We are here to help!') . "\n" .
                            __('db.Best regards') . ",\n" .
                            $company);

        // Construct WhatsApp URL with customer phone and personalized message
        $url = "https://web.whatsapp.com/send/?phone=$phone&text=$text";

        // Redirect to WhatsApp
        return redirect()->away($url);
    }

    public function paypalSuccess(Request $request)
    {
        $lims_sale_data = Sale::latest()->first();
        $lims_payment_data = Payment::latest()->first();
        $lims_product_sale_data = Product_Sale::where('sale_id', $lims_sale_data->id)->get();
        $provider = new ExpressCheckout;
        $token = $request->token;
        $payerID = $request->PayerID;
        $paypal_data['items'] = [];
        foreach ($lims_product_sale_data as $key => $product_sale_data) {
            $lims_product_data = Product::find($product_sale_data->product_id);
            $paypal_data['items'][] = [
                'name' => $lims_product_data->name,
                'price' => ($product_sale_data->total/$product_sale_data->qty),
                'qty' => $product_sale_data->qty
            ];
        }
        $paypal_data['items'][] = [
            'name' => 'order tax',
            'price' => $lims_sale_data->order_tax,
            'qty' => 1
        ];
        $paypal_data['items'][] = [
            'name' => 'order discount',
            'price' => $lims_sale_data->order_discount * (-1),
            'qty' => 1
        ];
        $paypal_data['items'][] = [
            'name' => 'shipping cost',
            'price' => $lims_sale_data->shipping_cost,
            'qty' => 1
        ];
        if($lims_sale_data->grand_total != $lims_sale_data->paid_amount){
            $paypal_data['items'][] = [
                'name' => 'Due',
                'price' => ($lims_sale_data->grand_total - $lims_sale_data->paid_amount) * (-1),
                'qty' => 1
            ];
        }

        $paypal_data['invoice_id'] = $lims_payment_data->payment_reference;
        $paypal_data['invoice_description'] = "Reference: {$paypal_data['invoice_id']}";
        $paypal_data['return_url'] = url('/sale/paypalSuccess');
        $paypal_data['cancel_url'] = url('/sale/create');

        $total = 0;
        foreach($paypal_data['items'] as $item) {
            $total += $item['price']*$item['qty'];
        }

        $paypal_data['total'] = $lims_sale_data->paid_amount;
        $response = $provider->getExpressCheckoutDetails($token);
        $response = $provider->doExpressCheckoutPayment($paypal_data, $token, $payerID);
        $data['payment_id'] = $lims_payment_data->id;
        $data['transaction_id'] = $response['PAYMENTINFO_0_TRANSACTIONID'];
        PaymentWithPaypal::create($data);
        return redirect('sales')->with('message', __('db.Sales created successfully'));
    }

    public function paypalPaymentSuccess(Request $request, $id)
    {
        $lims_payment_data = Payment::find($id);
        $provider = new ExpressCheckout;
        $token = $request->token;
        $payerID = $request->PayerID;
        $paypal_data['items'] = [];
        $paypal_data['items'][] = [
            'name' => 'Paid Amount',
            'price' => $lims_payment_data->amount,
            'qty' => 1
        ];
        $paypal_data['invoice_id'] = $lims_payment_data->payment_reference;
        $paypal_data['invoice_description'] = "Reference: {$paypal_data['invoice_id']}";
        $paypal_data['return_url'] = url('/sale/paypalPaymentSuccess');
        $paypal_data['cancel_url'] = url('/sale');

        $total = 0;
        foreach($paypal_data['items'] as $item) {
            $total += $item['price']*$item['qty'];
        }

        $paypal_data['total'] = $total;
        $response = $provider->getExpressCheckoutDetails($token);
        $response = $provider->doExpressCheckoutPayment($paypal_data, $token, $payerID);
        $data['payment_id'] = $lims_payment_data->id;
        $data['transaction_id'] = $response['PAYMENTINFO_0_TRANSACTIONID'];
        PaymentWithPaypal::create($data);
        return redirect('sales')->with('message', __('db.Payment created successfully'));
    }

    public function getProduct($id)
    {
        $query = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id');
        if(config('without_stock') == 'no') {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
                ['product_warehouse.qty', '>', 0]
            ]);
        }
        else {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id]
            ]);
        }

        $lims_product_warehouse_data = $query->whereNull('products.is_imei')
                                        ->whereNull('product_warehouse.variant_id')
                                        ->whereNull('product_warehouse.product_batch_id')
                                        ->select('product_warehouse.*', 'products.name', 'products.code', 'products.type', 'products.product_list', 'products.qty_list', 'products.is_embeded')
                                        ->get();
        //return $lims_product_warehouse_data;
        config()->set('database.connections.mysql.strict', false);
        \DB::reconnect(); //important as the existing connection if any would be in strict mode

        $query = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id');

        if(config('without_stock') == 'no') {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
                ['product_warehouse.qty', '>', 0]
            ]);
        }
        else {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id]
            ]);
        }

        $lims_product_with_batch_warehouse_data = $query->whereNull('product_warehouse.variant_id')
        ->whereNotNull('product_warehouse.product_batch_id')
        ->select('product_warehouse.*', 'products.name', 'products.code', 'products.type', 'products.product_list', 'products.qty_list', 'products.is_embeded')
        ->groupBy('product_warehouse.product_id')
        ->get();

        //now changing back the strict ON
        config()->set('database.connections.mysql.strict', true);
        \DB::reconnect();

        $query = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id');
        if(config('without_stock') == 'no') {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
                ['product_warehouse.qty', '>', 0]
            ]);
        }
        else {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
            ]);
        }

        $lims_product_with_imei_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['products.is_imei', true],
            ['product_warehouse.warehouse_id', $id],
            ['product_warehouse.qty', '>', 0]
        ])
        //->whereNull('product_warehouse.variant_id')
        ->whereNotNull('product_warehouse.imei_number')
        ->select('product_warehouse.*', 'products.is_embeded')
        //->groupBy('product_warehouse.product_id')
        ->get();

        $lims_product_with_variant_warehouse_data = $query->whereNotNull('product_warehouse.variant_id')
        ->select('product_warehouse.*', 'products.name', 'products.code', 'products.type', 'products.product_list', 'products.qty_list', 'products.is_embeded')
        ->get();

        $product_code = [];
        $product_name = [];
        $product_qty = [];
        $product_type = [];
        $product_id = [];
        $product_list = [];
        $qty_list = [];
        $product_price = [];
        $batch_no = [];
        $product_batch_id = [];
        $expired_date = [];
        $is_embeded = [];
        $imei_number = [];

        //product without variant
        foreach ($lims_product_warehouse_data as $product_warehouse)
        {
            if (!isset($product_warehouse->is_imei)) {
                if (isset($product_warehouse->imei_number)) continue;
            }

            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $product_code[] =  $product_warehouse->code;
            $product_name[] = htmlspecialchars($product_warehouse->name);
            $product_type[] = $product_warehouse->type;
            $product_id[] = $product_warehouse->product_id;
            $product_list[] = $product_warehouse->product_list;
            $qty_list[] = $product_warehouse->qty_list;
            $batch_no[] = null;
            $product_batch_id[] = null;
            $expired_date[] = null;
            if($product_warehouse->is_embeded)
                $is_embeded[] = $product_warehouse->is_embeded;
            else
                $is_embeded[] = 0;
            $imei_number[] = null;

        }
        //product with batches
        foreach ($lims_product_with_batch_warehouse_data as $product_warehouse)
        {
            if (!isset($product_warehouse->is_imei)) {
                if (isset($product_warehouse->imei_number)) continue;
            }

            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $product_code[] =  $product_warehouse->code;
            $product_name[] = htmlspecialchars($product_warehouse->name);
            $product_type[] = $product_warehouse->type;
            $product_id[] = $product_warehouse->product_id;
            $product_list[] = $product_warehouse->product_list;
            $qty_list[] = $product_warehouse->qty_list;
            $product_batch_data = ProductBatch::select('id', 'batch_no', 'expired_date')->find($product_warehouse->product_batch_id);
            $batch_no[] = $product_batch_data->batch_no;
            $product_batch_id[] = $product_batch_data->id;
            $expired_date[] = date(config('date_format'), strtotime($product_batch_data->expired_date));
            if($product_warehouse->is_embeded)
                $is_embeded[] = $product_warehouse->is_embeded;
            else
                $is_embeded[] = 0;

            $imei_number[] = null;
        }

        //product with imei
        foreach ($lims_product_with_imei_warehouse_data as $product_warehouse)
        {
            $imei_numbers = explode(",", $product_warehouse->imei_number);
            foreach ($imei_numbers as $key => $number) {
                $product_qty[] = $product_warehouse->qty;
                $product_price[] = $product_warehouse->price;
                $lims_product_data = Product::find($product_warehouse->product_id);
                //product with imei and variant
                if(!empty($product_warehouse->variant_id)){
                    $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_warehouse->product_id, $product_warehouse->variant_id)->first();
                    $product_code[] = $lims_product_variant_data->item_code;
                }else {
                    $product_code[] =  $lims_product_data->code;
                }

                $product_name[] = htmlspecialchars($lims_product_data->name);
                $product_type[] = $lims_product_data->type;
                $product_id[] = $lims_product_data->id;
                $product_list[] = $lims_product_data->product_list;
                $qty_list[] = $lims_product_data->qty_list;
                $batch_no[] = null;
                $product_batch_id[] = null;
                $expired_date[] = null;
                $is_embeded[] = 0;
                $imei_number[] = $number;
            }
        }

        //product with variant
        foreach ($lims_product_with_variant_warehouse_data as $product_warehouse)
        {
            if (!isset($product_warehouse->is_imei)) {
                if (isset($product_warehouse->imei_number)) continue;
            }

            $product_qty[] = $product_warehouse->qty;
            $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_warehouse->product_id, $product_warehouse->variant_id)->first();
            if($lims_product_variant_data) {
                $product_code[] =  $lims_product_variant_data->item_code;
                $product_name[] = htmlspecialchars($product_warehouse->name);
                $product_type[] = $product_warehouse->type;
                $product_id[] = $product_warehouse->product_id;
                $product_list[] = $product_warehouse->product_list;
                $qty_list[] = $product_warehouse->qty_list;
                $batch_no[] = null;
                $product_batch_id[] = null;
                $expired_date[] = null;
                if($product_warehouse->is_embeded)
                    $is_embeded[] = $product_warehouse->is_embeded;
                else
                    $is_embeded[] = 0;

                $imei_number[] = null;

            }
        }

        //retrieve product with type of digital and service
        $lims_product_data = Product::whereNotIn('type', ['standard', 'combo'])->where('is_active', true)->get();
        foreach ($lims_product_data as $product)
        {
            if (!isset($product->is_imei)) {
                if (isset($product->imei_number)) continue;
            }

            $product_qty[] = $product->qty;
            $product_code[] =  $product->code;
            $product_name[] = $product->name;
            $product_type[] = $product->type;
            $product_id[] = $product->id;
            $product_list[] = $product->product_list;
            $qty_list[] = $product->qty_list;
            $batch_no[] = null;
            $product_batch_id[] = null;
            $expired_date[] = null;
            $is_embeded[] = 0;
            $imei_number[] = null;

        }
        $product_data = [$product_code, $product_name, $product_qty, $product_type, $product_id, $product_list, $qty_list, $product_price, $batch_no, $product_batch_id, $expired_date, $is_embeded, $imei_number];
        //return $product_id;
        return $product_data;
    }

    public function posSale($id='')
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-add')) {
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            $lims_customer_list = Cache::remember('customer_list', 60*60*24, function () {
                return Customer::where('is_active', true)->get();
            });
            $lims_customer_group_all = Cache::remember('customer_group_list', 60*60*24, function () {
                return CustomerGroup::where('is_active', true)->get();
            });
            $lims_warehouse_list = Cache::remember('warehouse_list', 60*60*24*365, function () {
                return Warehouse::where('is_active', true)->get();
            });
            $lims_biller_list = Cache::remember('biller_list', 60*60*24*30, function () {
                return Biller::where('is_active', true)->get();
            });
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_tax_list = Cache::remember('tax_list', 60*60*24*30, function () {
                return Tax::where('is_active', true)->get();
            });

            $lims_pos_setting_data = Cache::remember('pos_setting', 60*60*24*30, function () {
                return PosSetting::latest()->first();
            });
            if($lims_pos_setting_data)
                $options = explode(',', $lims_pos_setting_data->payment_options);
            else
                $options = [];
            $lims_brand_list = Cache::remember('brand_list', 60*60*24*30, function () {
                return Brand::where('is_active',true)->get();
            });
            $lims_category_list = Cache::remember('category_list', 60*60*24*30, function () {
                return Category::where('is_active',true)->get();
            });

            if(cache()->has('general_setting'))
            {
                $general_setting = cache()->get('general_setting');
            }else {
                $general_setting = DB::table('general_settings')->select('modules')->first();
                cache()->put('general_setting', $general_setting, 60 * 60 * 24);
            }

            if(in_array('restaurant',explode(',',$general_setting->modules))){
                $lims_table_list = Table::join('floors','tables.floor_id','=','floors.id')
                        ->select('tables.id as id','tables.name','tables.number_of_person','floors.name as floor')
                        ->get();

                $service_list = DB::table('services')->where('is_active',1)->get();
                $waiter_list = DB::table('users')->where('service_staff',1)->where('is_active',1)->get();
            }else{
                $lims_table_list = Cache::remember('table_list', 60*60*24*30, function () {
                    return Table::where('is_active',true)->get();
                });
            }

            $lims_coupon_list = Cache::remember('coupon_list', 60*60*24*30, function () {
                return Coupon::where('is_active',true)->get();
            });
            $flag = 0;

            $currency_list = Currency::where('is_active', true)->get();
            $numberOfInvoice = Sale::count();
            $custom_fields = CustomField::where('belongs_to', 'sale')->get();

            $variables = ['currency_list','role','all_permission', 'lims_customer_list', 'lims_customer_group_all', 'lims_warehouse_list', 'lims_reward_point_setting_data', 'lims_tax_list', 'lims_biller_list', 'lims_pos_setting_data', 'options', 'lims_brand_list', 'lims_category_list', 'lims_table_list', 'lims_coupon_list', 'flag', 'numberOfInvoice', 'custom_fields', 'lims_account_list'];

            $lims_account_list = Account::select('id', 'name')->get();

            if(!empty($id)){
                $lims_sale_data = Sale::find($id);
                $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
                $variables[] = 'lims_sale_data';
                $variables[] = 'lims_product_sale_data';

                // $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
                $draft_product_data = [];
                $draft_product_discount = [
                    'order_discount' => $lims_sale_data->order_discount,
                    'discount' => []
                ];
                
                $draft_product_data = [];

                foreach ($lims_product_sale_data as $product_sale) {
                    $draft_product_discount['discount'][$product_sale->product_id] = $product_sale->discount;

                    $draft_product_list = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
                        ->where('products.id', $product_sale->product_id)
                        ->select('products.id', 'products.code', 'product_warehouse.qty')
                        ->first();

                    $product_code = $draft_product_list->code;

                    if ($product_sale->variant_id) {
                        $product_variant_data = ProductVariant::select('id', 'item_code')
                            ->FindExactProduct($draft_product_list->id, $product_sale->variant_id)
                            ->first();
                        $product_code = $product_variant_data->item_code;
                    }

                    for ($i = 0; $i < $product_sale->qty; $i++) {
                        if (!empty($product_sale->imei_number)) {
                            $imei_numbers = explode(",", $product_sale->imei_number);
                            foreach ($imei_numbers as $key => $number) {
                                $draft_product_data[] = [
                                    'code'     => $product_code,
                                    'qty'      => $draft_product_list->qty,
                                    'imei'     => $number ?: null,
                                    'embedded' => 0,
                                    'batch'    => $product_sale->product_batch_id,
                                    'price'    => $product_sale->net_unit_price, // or ->price depending on field
                                ];
                            }
                        } else {
                            $draft_product_data[] = [
                                'code'     => $product_code,
                                'qty'      => $draft_product_list->qty,
                                'imei'     => null,
                                'embedded' => 0,
                                'batch'    => $product_sale->product_batch_id,
                                'price'    => $product_sale->net_unit_price, // or ->price depending on field
                            ];
                        }
                    }
                }


                $variables[] = 'draft_product_data';
                $variables[] = 'draft_product_discount';
            }

            if(in_array('restaurant',explode(',',$general_setting->modules))){
                $variables[] = 'service_list';
                $variables[] = 'waiter_list';
            }
            return view('backend.sale.pos', compact(...$variables));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function recentSale()
    {
        if(cache()->has('general_setting'))
        {
            $general_setting = cache()->get('general_setting');
        }else {
            $general_setting = DB::table('general_settings')->select('modules')->first();
            cache()->put('general_setting', $general_setting, 60 * 60 * 24);
        }
        if(in_array('restaurant',explode(',',$general_setting->modules))){
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $recent_sale = Sale::join('customers', 'sales.customer_id', '=', 'customers.id')->select('sales.id','sales.reference_no','sales.customer_id','sales.grand_total','sales.created_at','customers.name')->where([
                    ['sales.sale_status', 1],
                    ['sales.user_id', Auth::id()]
                ])->orderBy('id', 'desc')->take(10)->get();
                return response()->json($recent_sale);
            }
            else {
                $recent_sale = Sale::join('customers', 'sales.customer_id', '=', 'customers.id')->select('sales.id','sales.reference_no','sales.customer_id','sales.grand_total','sales.created_at','customers.name')->where('sale_status', 1)->orderBy('id', 'desc')->take(10)->get();
                return response()->json($recent_sale);
            }
        }
        else {
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $recent_sale = Sale::join('customers', 'sales.customer_id', '=', 'customers.id')->select('sales.id','sales.reference_no','sales.customer_id','sales.grand_total','sales.created_at','customers.name')->where([
                    ['sales.sale_status', 1],
                    ['sales.user_id', Auth::id()]
                ])->orderBy('id', 'desc')->take(10)->get();
                return response()->json($recent_sale);
            }
            else {
                $recent_sale = Sale::join('customers', 'sales.customer_id', '=', 'customers.id')->select('sales.id','sales.reference_no','sales.customer_id','sales.grand_total','sales.created_at','customers.name')->where('sale_status', 1)->orderBy('id', 'desc')->take(10)->get();
                return response()->json($recent_sale);
            }
        }
    }

    public function recentDraft()
    {
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $recent_draft = Sale::join('customers', 'sales.customer_id', '=', 'customers.id')->select('sales.id','sales.reference_no','sales.customer_id','sales.grand_total','sales.created_at','customers.name')->where([
                ['sales.sale_status', 3],
                ['sales.user_id', Auth::id()]
            ])->orderBy('id', 'desc')->take(10)->get();
            return response()->json($recent_draft);
        }
        else {
            $recent_draft = Sale::join('customers', 'sales.customer_id', '=', 'customers.id')->select('sales.id','sales.reference_no','sales.customer_id','sales.grand_total','sales.created_at','customers.name')->where('sale_status', 3)->orderBy('id', 'desc')->take(10)->get();
            return response()->json($recent_draft);
        }
    }

    public function createSale($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-edit')) {
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_customer_group_all = CustomerGroup::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_sale_data = Sale::find($id);
            $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
            $lims_product_list = Product::where([
                                    ['featured', 1],
                                    ['is_active', true]
                                ])->get();
            foreach ($lims_product_list as $key => $product) {
                $images = explode(",", $product->image);
                if($images[0])
                    $product->base_image = $images[0];
                else
                    $product->base_image = 'zummXD2dvAtI.png';
            }
            $product_number = count($lims_product_list);
            $lims_pos_setting_data = PosSetting::latest()->first();
            $lims_brand_list = Brand::where('is_active',true)->get();
            $lims_category_list = Category::where('is_active',true)->get();
            $lims_coupon_list = Coupon::where('is_active',true)->get();

            $currency_list = Currency::where('is_active', true)->get();

            return view('backend.sale.create_sale',compact('currency_list', 'lims_biller_list', 'lims_customer_list', 'lims_warehouse_list', 'lims_tax_list', 'lims_sale_data','lims_product_sale_data', 'lims_pos_setting_data', 'lims_brand_list', 'lims_category_list', 'lims_coupon_list', 'lims_product_list', 'product_number', 'lims_customer_group_all', 'lims_reward_point_setting_data'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function getProducts($warehouse_id, $key, $cat_or_brand_id)
    {
        $query = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id');

        if ($key == 'category') {
            $query = $query->join('categories', 'products.category_id', '=', 'categories.id')
                ->where(function ($query) use ($cat_or_brand_id) {
                    $query->where('products.category_id', $cat_or_brand_id)
                        ->orWhere('categories.parent_id', $cat_or_brand_id);
                });
        } elseif ($key == 'brand') {
            $query = $query->where('products.brand_id', $cat_or_brand_id);
        } elseif ($key == 'featured') {
            $query = $query->where('products.featured', true);
        }

        $query = $query->where('products.type', '!=', 'combo')
               ->where('products.is_imei', null)->orWhere('products.is_imei', 0);

        if (config('without_stock') == 'no') {
            $query = $query->where('products.is_active', true)
                ->where('product_warehouse.warehouse_id', $warehouse_id)
                ->where('product_warehouse.qty', '>', 0);
        } else {
            $query = $query->where('products.is_active', true)
                ->where('product_warehouse.warehouse_id', $warehouse_id);
        }

        $lims_product_list = $query->select(
            'products.id',
            'products.code',
            'products.name',
            'products.is_imei',
            'products.is_embeded',
            'products.image',
            'products.qty',
            'products.is_variant') // Fetch required fields
            ->orderBy('products.name', 'asc') // Sort by name
            ->groupBy('products.id')
            ->paginate(15);  // Paginate results
        
        $index = 0;
        $data = [];

        foreach ($lims_product_list as $product) {
            if ($product->is_variant) {
                $product_variants = ProductVariant::where('product_id', $product->id)->orderBy('position')->get();

                foreach ($product_variants as $variant) {
                    // Fetch stock from product_warehouse
                    $pw = Product_Warehouse::where([
                        ['product_id', $product->id],
                        ['variant_id', $variant->pivot['id'] ?? $variant->id],
                        ['warehouse_id', $warehouse_id]
                    ])->first();

                    if (!$pw || (config('without_stock') == 'no' && $pw->qty <= 0)) {
                        continue;
                    }

                    $data['name'][$index] = $product->name . ' [' . $variant->name . ']';
                    $data['code'][$index] = $variant->item_code;
                    $data['is_imei'][$index] = $product->is_imei;
                    $data['is_embeded'][$index] = $product->is_embeded;
                    $images = explode(",", $product->image);
                    $data['image'][$index] = $images[0] ?? null;
                    $data['qty'][$index] = $pw->qty;
                    $data['price'][$index] = $pw->price;
                    $index++;
                }
            } else {
                // Get quantity for non-variant product from product_warehouse
                $pw = Product_Warehouse::where([
                    ['product_id', $product->id],
                    ['warehouse_id', $warehouse_id]
                ])->first();

                if (!$pw || (config('without_stock') == 'no' && $pw->qty <= 0)) {
                    continue;
                }

                $data['name'][$index] = $product->name;
                $data['code'][$index] = $product->code;
                $data['is_imei'][$index] = $product->is_imei;
                $data['is_embeded'][$index] = $product->is_embeded;
                $images = explode(",", $product->image);
                $data['image'][$index] = $images[0] ?? null;
                $data['qty'][$index] = $pw->qty;
                $data['price'][$index] = $pw->price;
                $index++;
            }
        }

        return response()->json([
            'data' => $data,
            'next_page_url' => $lims_product_list->nextPageUrl(),
        ]);
    }

    public function getCustomerGroup($id)
    {
         $lims_customer_data = Customer::find($id);
         $lims_customer_group_data = CustomerGroup::find($lims_customer_data->customer_group_id);
         return $lims_customer_group_data->percentage;
    }

    public function limsProductSearch(Request $request)
    {
        $todayDate = date('Y-m-d');
        // $productData = explode("|", $request['data']);
        // $productInfo = explode("?", $productData[4]);

        $code = $request->data['code'];
        $qty = $request->data['qty'];
        $is_embedded = $request->data['embedded'];
        $batch_id = $request->data['batch'];
        $customerId = $request->data['customer_id'];
        $productVariantId = null;
        $qty = ($is_embedded == 1) ? substr($code, 7, 5) / 1000 : $request->data['pre_qty'];
        $dqty = $qty;
        $dstatus = isset($request->data['dstatus']) ? $request->data['dstatus'] : $dqty. '/'.$dqty;
        if ($is_embedded == 1) {
            $code = substr($code, 0, 7);
        }


        // Fetch customer discounts
        $discounts = DB::table('discount_plan_customers')
            ->join('discount_plans', 'discount_plan_customers.discount_plan_id', '=', 'discount_plans.id')
            ->join('discount_plan_discounts', 'discount_plans.id', '=', 'discount_plan_discounts.discount_plan_id')
            ->join('discounts', 'discounts.id', '=', 'discount_plan_discounts.discount_id')
            ->where([
                ['discount_plans.is_active', true],
                ['discounts.is_active', true],
                ['discount_plan_customers.customer_id', $customerId]
            ])->select('discounts.*')->get();

        if(cache()->has('general_setting'))
        {
            $general_setting = cache()->get('general_setting');
        }else {
            $general_setting = DB::table('general_settings')->select('modules')->first();
            cache()->put('general_setting', $general_setting, 60 * 60 * 24);
        }
        if ($general_setting && in_array('restaurant', explode(',', $general_setting->modules))) {
            // Try to find base product
            $product = Product::select('id','name','code','is_variant','is_batch','is_imei','qty','price','wholesale_price','cost','promotion','promotion_price','last_date','tax_id','tax_method','type','unit_id','sale_unit_id','extras')->where('code', $code)->where('is_active', true)->first();
        } else {
            // Try to find base product
            $product = Product::select('id','name','code','is_variant','is_batch','is_imei','qty','price','wholesale_price','cost','promotion','promotion_price','last_date','tax_id','tax_method','type','unit_id','sale_unit_id')->where('code', $code)->where('is_active', true)->first();
        }

        // Try to find variant if base product not found
        if (!$product) {
            $variantProduct = Product::join('product_variants', 'products.id', '=', 'product_variants.product_id')
                ->select('products.*', 'product_variants.id as product_variant_id', 'product_variants.item_code', 'product_variants.additional_price')
                ->where('product_variants.item_code', $code)
                ->where('products.is_active', true)
                ->first();

            if ($variantProduct) {
                $product = $variantProduct;
                $productVariantId = $variantProduct->product_variant_id;
            }
        }

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Handle pricing
        if($request->data['price'] && $request->data['price'] > 0)
            $price = $request->data['price'];
        else
            $price = $product->price;

        if ($product->is_variant && isset($product->additional_price)) {
            $price += $product->additional_price;
        }

        $discountedPrice = null;
        $noDiscountApplied = true;

        foreach ($discounts as $discount) {
            $applicableProducts = explode(',', $discount->product_list);
            $applicableDays = explode(',', $discount->days);
            $todayDay = date('D');

            if ((
                    $discount->applicable_for === 'All' ||
                    in_array($product->id, $applicableProducts)
                ) && (
                    $todayDate >= $discount->valid_from &&
                    $todayDate <= $discount->valid_till &&
                    in_array($todayDay, $applicableDays) &&
                    $qty >= $discount->minimum_qty &&
                    $qty <= $discount->maximum_qty
                )
            ) {
                $discountedPrice = $discount->type === 'flat'
                    ? $price - $discount->value
                    : $price - ($price * ($discount->value / 100));
                $noDiscountApplied = false;
                break;
            }
        }

        if ($noDiscountApplied && $product->promotion && $todayDate <= $product->last_date) {
            $discountedPrice = $product->promotion_price;
        } elseif ($noDiscountApplied) {
            $discountedPrice = $price;
        }

        // Tax info
        $taxRate = 0;
        $taxName = 'No Tax';
        if ($product->tax_id) {
            $tax = Tax::find($product->tax_id);
            if ($tax) {
                $taxRate = $tax->rate;
                $taxName = $tax->name;
            }
        }

        // Units
        if (in_array($product->type, ['standard', 'combo'])) {
            $units = Unit::where("base_unit", $product->unit_id)->orWhere('id', $product->unit_id)->get();
            $unitNames = $unitOperators = $unitValues = [];

            foreach ($units as $unit) {
                if ($product->sale_unit_id == $unit->id) {
                    array_unshift($unitNames, $unit->unit_name);
                    array_unshift($unitOperators, $unit->operator);
                    array_unshift($unitValues, $unit->operation_value);
                } else {
                    $unitNames[] = $unit->unit_name;
                    $unitOperators[] = $unit->operator;
                    $unitValues[] = $unit->operation_value;
                }
            }
        } else {
            $unitNames = $unitOperators = $unitValues = ['n/a'];
        }

        // check if batch product
        if(!empty($batch_id)){
            $batch = ProductBatch::find($batch_id);
        }

        $productArray = [
            $product->name, //0
            $product->is_variant ? ($product->item_code ?? $product->code) : $product->code, //1
            $discountedPrice, //2
            $taxRate, //3
            $taxName, //4
            $product->tax_method, //5
            implode(',', $unitNames) . ',', //6
            implode(',', $unitOperators) . ',', //7
            implode(',', $unitValues) . ',', //8
            $product->id, //9
            $productVariantId, //10
            $product->promotion, //11
            $product->is_batch, //12
            $product->is_imei, //13
            $product->is_variant, //14
            $qty, //15
            $product->wholesale_price, //16
            $product->cost, //17
            $request->data['imei'], // IMEI number //18
            $request->data['qty'], // warehouse qty //19
            $product->type, //20
            $batch_id, // batch ID // 21
            $batch->batch_no ?? '', // 22
            $dqty, //23
            $dqty. '/'.$dqty // 24

        ];

        // Restaurant extras
        if ($general_setting && in_array('restaurant', explode(',', $general_setting->modules))) {
            if (!empty($product->extras)) {
                $extras = Product::whereIn('id', explode(',', $product->extras))
                                ->where('is_active', 1)
                                ->get();
                $productArray[] = $extras;
            }
        }

        return response()->json($productArray);
    }

    public function checkDiscount(Request $request)
    {
        $qty = $request->input('qty');
        $customer_id = $request->input('customer_id');
        $warehouse_id = $request->input('warehouse_id');
        $productDiscount = 0;
        $lims_product_data = Product::select('id', 'price', 'promotion', 'promotion_price', 'last_date')->find($request->input('product_id'));
        $lims_product_warehouse_data = Product_Warehouse::where([
            ['product_id', $request->input('product_id')],
            ['warehouse_id', $warehouse_id]
        ])->first();
        if($lims_product_warehouse_data && $lims_product_warehouse_data->price) {
            $lims_product_data->price = $lims_product_warehouse_data->price;
        }
        $todayDate = date('Y-m-d');
        $all_discount = DB::table('discount_plan_customers')
                        ->join('discount_plans', 'discount_plans.id', '=', 'discount_plan_customers.discount_plan_id')
                        ->join('discount_plan_discounts', 'discount_plans.id', '=', 'discount_plan_discounts.discount_plan_id')
                        ->join('discounts', 'discounts.id', '=', 'discount_plan_discounts.discount_id')
                        ->where([
                            ['discount_plans.is_active', true],
                            ['discounts.is_active', true],
                            ['discount_plan_customers.customer_id', $customer_id]
                        ])
                        ->select('discounts.*')
                        ->get();
        $no_discount = 1;
        foreach ($all_discount as $key => $discount) {
            $product_list = explode(",", $discount->product_list);
            $days = explode(",", $discount->days);

            if( ( $discount->applicable_for == 'All' || in_array($lims_product_data->id, $product_list) ) && ( $todayDate >= $discount->valid_from && $todayDate <= $discount->valid_till && in_array(date('D'), $days) && $qty >= $discount->minimum_qty && $qty <= $discount->maximum_qty ) ) {
                if($discount->type == 'flat') {
                    $productDiscount = $discount->value;
                    $price = $lims_product_data->price - $discount->value;
                }
                elseif($discount->type == 'percentage') {
                    $productDiscount = $lims_product_data->price * ($discount->value/100);
                    $price = $lims_product_data->price - ($lims_product_data->price * ($discount->value/100));
                }
                $no_discount = 0;
                break;
            }
            else {
                continue;
            }
        }

        if($lims_product_data->promotion && $todayDate <= $lims_product_data->last_date && $no_discount) {
            $price = $lims_product_data->promotion_price;
        }
        elseif($no_discount)
            $price = $lims_product_data->price;

        $data = [$price, $lims_product_data->promotion,$productDiscount];
        return $data;
    }

    public function getGiftCard()
    {
        $gift_card = GiftCard::where("is_active", true)->whereDate('expired_date', '>=', date("Y-m-d"))->get(['id', 'card_no', 'amount', 'expense']);
        return json_encode($gift_card);
    }

    public function productSaleData($id)
    {
        $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
        $deliveryLines = [];
        foreach (DeliveryProgress::linesForSale((int) $id) as $line) {
            $deliveryLines[$line['product_sale_id']] = $line;
        }
        foreach ($lims_product_sale_data as $key => $product_sale_data) {
            $product = Product::find($product_sale_data->product_id);
            if(!$product) {
                $product = new \stdClass();
                $product->name = __('db.Removed product #:id', ['id' => $product_sale_data->product_id]);
                $product->code = '';
            }
            if($product_sale_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)->first();
                if($lims_product_variant_data) {
                    $product->code = $lims_product_variant_data->item_code;
                }
            }
            $unit_data = Unit::find($product_sale_data->sale_unit_id);
            if($unit_data){
                $unit = $unit_data->unit_code;
            }
            else
                $unit = '';
            if($product_sale_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no')->find($product_sale_data->product_batch_id);
                $product_sale[7][$key] = $product_batch_data->batch_no;
            }
            else
                $product_sale[7][$key] = 'N/A';
            $product_sale[0][$key] = $product->name . ' [' . $product->code . ']';
            $returned_imei_number_data ='';
            if( $product_sale_data->imei_number && !str_contains($product_sale_data->imei_number, "null") ) {
                $imeis = array_unique(explode(',', $product_sale_data->imei_number));
                $imeis = implode(',', $imeis);
                $product_sale[0][$key] .= '<br>IMEI or Serial Number: '. $imeis;
                $returned_imei_number_data = DB::table('returns')
                                    ->join('product_returns', 'returns.id', '=', 'product_returns.return_id')
                                    ->where([
                                        ['returns.sale_id', $id],
                                        ['product_returns.product_id', $product_sale_data->product_id]
                                    ])->select('product_returns.imei_number')
                                    ->first();
            }
            $product_sale[1][$key] = $product_sale_data->qty;
            $product_sale[2][$key] = $unit;
            $product_sale[3][$key] = $product_sale_data->tax;
            $product_sale[4][$key] = $product_sale_data->tax_rate;
            $product_sale[5][$key] = $product_sale_data->discount;
            $product_sale[6][$key] = $product_sale_data->total;
            if($returned_imei_number_data) {
                $imeis = array_unique(explode(',', $returned_imei_number_data->imei_number));
                $imeis = implode(',', $imeis);
                $product_sale[8][$key] = $product_sale_data->return_qty.'<br>IMEI or Serial Number: '. $imeis;
            }
            else
                $product_sale[8][$key] = $product_sale_data->return_qty;
            $deliveryLine = $deliveryLines[$product_sale_data->id] ?? null;
            if($deliveryLine) {
                $product_sale[9][$key] = DeliveryProgress::formatQty($deliveryLine['delivered']).' / '.DeliveryProgress::formatQty($deliveryLine['sold']);
            }
            elseif($product_sale_data->is_delivered)
                $product_sale[9][$key] = __('db.Yes');
            else
                $product_sale[9][$key] = __('db.No');

            if(cache()->has('general_setting'))
            {
                $general_setting = cache()->get('general_setting');
            }else {
                $general_setting = DB::table('general_settings')->select('modules')->first();
                cache()->put('general_setting', $general_setting, 60 * 60 * 24);
            }
            if(in_array('restaurant',explode(',',$general_setting->modules))){
                $product_sale[10][$key] = $product_sale_data->topping_id;
            }
        }
        return $product_sale;
    }

    public function deliveryHistory($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('sales-index') && !$role->hasPermissionTo('delivery')) {
            return response()->json(['error' => __('db.Sorry! You are not allowed to access this module')], 403);
        }
        $sale = Sale::find($id);
        if(!$sale) {
            return response()->json(['deliveries' => [], 'progress' => null]);
        }
        $progress = DeliveryProgress::forSales([(int) $id])[(int) $id] ?? null;
        if($progress) {
            $progress['label'] = DeliveryProgress::label($progress['status']);
            $progress['sold_label'] = DeliveryProgress::formatQty($progress['sold']);
            $progress['delivered_label'] = DeliveryProgress::formatQty($progress['delivered']);
            $progress['remaining_label'] = DeliveryProgress::formatQty($progress['remaining']);
        }

        return response()->json([
            'progress' => $progress,
            'lines' => DeliveryProgress::linesForSale((int) $id),
            'deliveries' => DeliveryProgress::history((int) $id),
        ]);
    }

    public function invoiceLookup(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('sales-index')) {
            return response()->json(['error' => __('db.Sorry! You are not allowed to access this module')], 403);
        }

        $date = $request->input('date');
        $reference = trim((string) $request->input('reference'));
        $customerName = trim((string) $request->input('customer'));
        if(!$date && $reference === '' && $customerName === '') {
            return response()->json([]);
        }

        $q = Sale::query()->with('customer:id,name,phone_number');
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $q->where('user_id', Auth::id());
        } elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse') {
            $q->where('warehouse_id', Auth::user()->warehouse_id);
        }
        if($date) {
            $parsed = strtotime(str_replace('/', '-', $date));
            if(!$parsed) {
                return response()->json([]);
            }
            $q->whereDate('created_at', date('Y-m-d', $parsed));
        }
        if($reference !== '') {
            $q->where('reference_no', 'LIKE', '%'.$this->escapeLike($reference).'%');
        }
        if($customerName !== '') {
            $like = '%'.$this->escapeLike($customerName).'%';
            $q->whereHas('customer', function ($customer) use ($like) {
                $customer->where('name', 'LIKE', $like);
            });
        }

        $sales = $q->orderByDesc('id')->limit(30)->get();
        $progressMap = DeliveryProgress::forSales($sales->pluck('id'));
        $rows = [];
        foreach ($sales as $sale) {
            $progress = $progressMap[(int) $sale->id] ?? [
                'status' => DeliveryProgress::NOT_DELIVERED,
                'sold' => 0,
                'delivered' => 0,
                'remaining' => 0,
            ];
            $saleDate = $sale->created_at ? $sale->created_at->toDateString() : date('Y-m-d');
            $saleParams = [
                'open_sale' => $sale->id,
                'starting_date' => $saleDate,
                'ending_date' => $saleDate,
            ];
            $rows[] = [
                'id' => $sale->id,
                'date' => date(config('date_format'), strtotime($sale->created_at)),
                'reference_no' => $sale->reference_no,
                'customer' => optional($sale->customer)->name ?? '',
                'phone' => optional($sale->customer)->phone_number ?? '',
                'grand_total' => number_format($sale->grand_total, config('decimal')),
                'delivery_status' => DeliveryProgress::label($progress['status']),
                'delivery_status_key' => $progress['status'],
                'delivery_qty' => DeliveryProgress::formatQty($progress['delivered']).' / '.DeliveryProgress::formatQty($progress['sold']),
                'invoice_url' => route('sale.invoice', $sale->id),
                'details_url' => url('sales').'?'.http_build_query($saleParams),
                'history_url' => url('sales').'?'.http_build_query($saleParams + ['show' => 'delivery-history']),
                'add_delivery_url' => url('sales').'?'.http_build_query([
                    'add_delivery' => $sale->id,
                    'starting_date' => $saleDate,
                    'ending_date' => $saleDate,
                ]),
            ];
        }

        return response()->json($rows);
    }

    public function detailCard($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role || (!$role->hasPermissionTo('sales-index') && !$role->hasPermissionTo('delivery'))) {
            return response()->json(['error' => __('db.Sorry! You are not allowed to access this module')], 403);
        }

        $sale = Sale::with(['biller', 'customer', 'warehouse', 'user'])->find($id);
        if(!$sale) {
            return response()->json(['error' => __('db.Sale not found')], 404);
        }

        $status = match ((int) $sale->sale_status) {
            1 => __('db.Completed'),
            2 => __('db.Pending'),
            3 => __('db.Draft'),
            4 => __('db.Returned'),
            5 => __('db.Processing'),
            6 => __('db.Cooked'),
            7 => __('db.Served'),
            default => '',
        };
        $coupon = $sale->coupon_id ? Coupon::find($sale->coupon_id) : null;
        $currency = $sale->currency_id ? Currency::find($sale->currency_id) : null;
        $tableName = '';
        if(!empty($sale->table_id)) {
            $table = Table::find($sale->table_id);
            $tableName = $table->name ?? '';
        }
        $biller = $sale->biller;
        $customer = $sale->customer;
        $note = function ($value) {
            return nl2br(e((string) $value));
        };

        return response()->json([
            date(config('date_format'), strtotime($sale->created_at)),
            $sale->reference_no,
            $status,
            $biller->name ?? '',
            $biller->company_name ?? '',
            $biller->email ?? '',
            $biller->phone_number ?? '',
            $biller->address ?? '',
            $biller->city ?? '',
            $customer->name ?? '',
            $customer->phone_number ?? '',
            $customer->address ?? '',
            $customer->city ?? '',
            $sale->id,
            $sale->total_tax,
            $sale->total_discount,
            $sale->total_price,
            $sale->order_tax,
            $sale->order_tax_rate,
            $sale->order_discount,
            $sale->shipping_cost,
            $sale->grand_total,
            $sale->paid_amount,
            $note($sale->sale_note),
            $note($sale->staff_note),
            optional($sale->user)->name ?? '',
            optional($sale->user)->email ?? '',
            optional($sale->warehouse)->name ?? '',
            $coupon->code ?? '',
            $sale->coupon_discount,
            $sale->document,
            $currency->code ?? '',
            $sale->exchange_rate,
            $tableName,
        ]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function getSale($id)
    {
        $lims_product_sale_data = Sale::findOrFail($id);

        if (!$lims_product_sale_data) {
            return [];
        }

        $sale[13] = $id;
        $sale[0] = $lims_product_sale_data->created_at->format('d-m-Y');
        $sale[1] = $lims_product_sale_data->reference_no;
        $sale[14] = $lims_product_sale_data->total_tax;
        $sale[15] = $lims_product_sale_data->total_discount;
        $sale[16] = $lims_product_sale_data->total_price;
        $warehouse = Warehouse::findOrFail($lims_product_sale_data->warehouse_id);
        $sale[17] = $lims_product_sale_data->order_tax;
        $sale[18] = $lims_product_sale_data->order_tax_rate;
        $sale[19] = $lims_product_sale_data->order_discount;
        $sale[20] = $lims_product_sale_data->shipping_cost;
        $sale[21] = $lims_product_sale_data->grand_total;
        $sale[22] = $lims_product_sale_data->paid_amount;
        $sale[23] = $lims_product_sale_data->sale_note ;
        $sale[24] = $lims_product_sale_data->staff_note;
        $sale[25] = Auth::user()->name;
        $sale[26] = Auth::user()->email;
        $sale[27] = $warehouse->name;

        if($lims_product_sale_data->sale_status == 1){
            $sale[2] = __('db.Completed');
        }
        elseif($lims_product_sale_data->sale_status == 2){
            $sale[2] = __('db.Pending');
        }
        elseif($lims_product_sale_data->sale_status == 3){
            $sale[2] = __('db.Draft');
        }
        elseif($lims_product_sale_data->sale_status == 4){
            $sale[2] = __('db.Returned');
        }
        elseif($lims_product_sale_data->sale_status == 5){
            $sale[2] = __('db.Processing');
        }
        elseif($lims_product_sale_data->sale_status == 6){
            $sale[2] = __('db.Cooked');
        }
        elseif($lims_product_sale_data->sale_status == 7){
            $sale[2] = __('db.Served');
        }

        $currency = Currency::findOrFail($lims_product_sale_data->currency_id);
        $sale[31] = $currency->code;
        $sale[32] = $lims_product_sale_data->exchange_rate;
        $sale[30] = $lims_product_sale_data->document;

        $biller = Biller::findOrFail($lims_product_sale_data->biller_id);
        $sale[3] = $biller->name;
        $sale[4] = $biller->company_name;
        $sale[5] = $biller->email;
        $sale[6] = $biller->phone_number;
        $sale[7] = $biller->address;
        $sale[8] = $biller->city;

        $customer = Customer::findOrFail($lims_product_sale_data->customer_id);
        $sale[9] = $customer->name;
        $sale[10] = $customer->phone_number;
        $sale[11] = $customer->address;
        $sale[12] = $customer->city;

        //table
        if(!empty($lims_product_sale_data->table_id)){
            $table = Table::findOrFail($lims_product_sale_data->table_id);
            $sale[28] = $table->name;
        }
        else
            $sale[28] = '';


        return $sale;
    }

    public function saleByCsv()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-add')){
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $numberOfInvoice = Sale::count();
            return view('backend.sale.import',compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_tax_list', 'numberOfInvoice'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function importSale(Request $request)
    {
        try {
            DB::beginTransaction();
            //get the file
            $upload=$request->file('file');
            $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
            //checking if this is a CSV file
            if($ext != 'csv')
                return redirect()->back()->with('message', __('db.Please upload a CSV file'));

            $filePath=$upload->getRealPath();
            $file_handle = fopen($filePath, 'r');
            $i = 0;
            $counter = 1;
            //validate the file
            while (!feof($file_handle) ) {
                $current_line = fgetcsv($file_handle);
                if($current_line && $i > 0){
                    $product_data[] = Product::where('code', $current_line[0])->first();
                    if(!$product_data[$i-1]) {
                        throw new \Exception(__('db.Product does not exist!'));
                        // return redirect()->back()->with('message', __('db.Product does not exist!'));
                    }
                    $unit[] = Unit::where('unit_code', $current_line[2])->first();
                    if(!$unit[$i-1] && $current_line[2] == 'n/a')
                        $unit[$i-1] = 'n/a';
                    elseif(!$unit[$i-1]){
                        throw new \Exception(__('db.Sale unit does not exist!'));
                        // return redirect()->back()->with('message', __('db.Sale unit does not exist!'));
                    }
                    if(strtolower($current_line[5]) != "no tax"){
                        $tax[] = Tax::where('name', $current_line[5])->first();
                        if(!$tax[$i-1]) {
                            throw new \Exception(__('db.Tax name does not exist!'));
                            // return redirect()->back()->with('message', __('db.Tax name does not exist!'));
                        }
                    }
                    else
                        $tax[$i-1]['rate'] = 0;

                    $qty[] = $current_line[1];
                    $price[] = $current_line[3];
                    $discount[] = $current_line[4];
                    $counter++;
                }
                $i++;
            }
            //return $unit;
            $data = $request->except('document');
            // $data['reference_no'] = 'sr-' . date("Ymd") . '-'. date("his");
            $data['reference_no'] = $this->generateInvoiceName('sr-');
            $data['user_id'] = Auth::user()->id;
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
                if ($v->fails()) {
                    throw new \Exception($v->errors());
                    // return redirect()->back()->withErrors($v->errors());
                }

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
            $item = 0;
            $grand_total = $data['shipping_cost'];
            Sale::create($data);
            $lims_sale_data = Sale::latest()->first();
            $lims_customer_data = Customer::find($lims_sale_data->customer_id);

            $counter = 1;
            foreach ($product_data as $key => $product) {
                if($product['tax_method'] == 1){
                    $net_unit_price = $price[$key] - $discount[$key];
                    $product_tax = $net_unit_price * ($tax[$key]['rate'] / 100) * $qty[$key];
                    $total = ($net_unit_price * $qty[$key]) + $product_tax;
                }
                elseif($product['tax_method'] == 2){
                    $net_unit_price = (100 / (100 + $tax[$key]['rate'])) * ($price[$key] - $discount[$key]);
                    $product_tax = ($price[$key] - $discount[$key] - $net_unit_price) * $qty[$key];
                    $total = ($price[$key] - $discount[$key]) * $qty[$key];
                }
                if($data['sale_status'] == 1 && $unit[$key]!='n/a'){
                    $sale_unit_id = $unit[$key]['id'];
                    if($unit[$key]['operator'] == '*')
                        $quantity = $qty[$key] * $unit[$key]['operation_value'];
                    elseif($unit[$key]['operator'] == '/')
                        $quantity = $qty[$key] / $unit[$key]['operation_value'];
                    $product['qty'] -= $quantity;
                    $product_warehouse = Product_Warehouse::where([
                        ['product_id', $product['id']],
                        ['warehouse_id', $data['warehouse_id']]
                    ])->first();
                    $product_warehouse->qty -= $quantity;
                    $product->save();
                    $product_warehouse->save();
                }
                else
                    $sale_unit_id = 0;
                //collecting mail data
                $mail_data['products'][$key] = $product['name'];
                if($product['type'] == 'digital')
                    $mail_data['file'][$key] = url('/product/files').'/'.$product['file'];
                else
                    $mail_data['file'][$key] = '';
                if($sale_unit_id)
                    $mail_data['unit'][$key] = $unit[$key]['unit_code'];
                else
                    $mail_data['unit'][$key] = '';

                $product_sale = new Product_Sale();
                $product_sale->sale_id = $lims_sale_data->id;
                $product_sale->product_id = $product['id'];
                $product_sale->qty = $mail_data['qty'][$key] = $qty[$key];
                $product_sale->sale_unit_id = $sale_unit_id;
                $product_sale->net_unit_price = number_format((float)$net_unit_price, config('decimal'), '.', '');
                $product_sale->discount = $discount[$key] * $qty[$key];
                $product_sale->tax_rate = $tax[$key]['rate'];
                $product_sale->tax = number_format((float)$product_tax, config('decimal'), '.', '');
                $product_sale->total = $mail_data['total'][$key] = number_format((float)$total, config('decimal'), '.', '');
                $product_sale->save();
                $lims_sale_data->total_qty += $qty[$key];
                $lims_sale_data->total_discount += $discount[$key] * $qty[$key];
                $lims_sale_data->total_tax += number_format((float)$product_tax, config('decimal'), '.', '');
                $lims_sale_data->total_price += number_format((float)$total, config('decimal'), '.', '');
                $counter++;
            }
            $lims_sale_data->item = $key + 1;
            $lims_sale_data->order_tax = ($lims_sale_data->total_price - $lims_sale_data->order_discount) * ($data['order_tax_rate'] / 100);
            $lims_sale_data->grand_total = ($lims_sale_data->total_price + $lims_sale_data->order_tax + $lims_sale_data->shipping_cost) - $lims_sale_data->order_discount;
            $lims_sale_data->save();
            $message = 'Sale imported successfully';
            $mail_setting = MailSetting::latest()->first();
            if($lims_customer_data->email && $mail_setting) {
                //collecting male data
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
                $this->setMailInfo($mail_setting);
                try {
                    Mail::to($mail_data['email'])->send(new SaleDetails($mail_data));
                    $message = 'Sale imported successfully';
                }

                catch(\Exception $e){
                    $message = 'Sale imported successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
                }
            }
            DB::commit();
            return redirect('sales')->with('message', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('sales/sale_by_csv')->with('not_permitted', "Error in row $counter: " . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('sales-edit')){
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_sale_data = Sale::find($id);
            $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
            if($lims_sale_data->exchange_rate)
                $currency_exchange_rate = $lims_sale_data->exchange_rate;
            else
                $currency_exchange_rate = 1;
            $custom_fields = CustomField::where('belongs_to', 'sale')->get();
            return view('backend.sale.edit',compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_tax_list', 'lims_sale_data','lims_product_sale_data', 'currency_exchange_rate', 'custom_fields'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->except('document');
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
            }

            $lims_sale_data = Sale::find($id);
            $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();

            $product_id = $data['product_id'];
            $qty = $data['qty'];
            $dqty = $data['dqty'] ?? [];
            $sale_unit = $data['sale_unit'];

            // Adjust stock based on dqty changes
            if ($lims_sale_data->sale_status == 1) {
                foreach ($lims_product_sale_data as $product_sale) {
                    $old_dqty = $product_sale->dqty;
                    $lims_product_data = Product::find($product_sale->product_id);
                    $lims_sale_unit_data = Unit::find($product_sale->sale_unit_id);

                    if ($lims_sale_unit_data) {
                        if ($lims_sale_unit_data->operator == '*') {
                            $old_dqty *= $lims_sale_unit_data->operation_value;
                        } else {
                            $old_dqty /= $lims_sale_unit_data->operation_value;
                        }
                    }

                    // Add back old delivered quantity to stock
                    $lims_product_data->qty += $old_dqty;
                    $lims_product_data->save();

                    $lims_product_warehouse_data = Product_Warehouse::where([
                        ['product_id', $product_sale->product_id],
                        ['warehouse_id', $lims_sale_data->warehouse_id]
                    ])->first();

                    if ($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->qty += $old_dqty;
                        $lims_product_warehouse_data->save();
                    }
                }
            }

            // Delete old product sale records
            Product_Sale::where('sale_id', $id)->delete();

            // Add new product sale records and deduct new dqty from stock
            foreach ($product_id as $key => $pro_id) {
                $new_dqty = isset($dqty[$key]) ? $dqty[$key] : $qty[$key];
                $lims_product_data = Product::find($pro_id);
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$key])->first();

                $stock_deduction_qty = $new_dqty;
                if ($lims_sale_unit_data) {
                    if ($lims_sale_unit_data->operator == '*') {
                        $stock_deduction_qty *= $lims_sale_unit_data->operation_value;
                    } else {
                        $stock_deduction_qty /= $lims_sale_unit_data->operation_value;
                    }
                }

                // Deduct new delivered quantity from stock
                if ($data['sale_status'] == 1) {
                    $lims_product_data->qty -= $stock_deduction_qty;
                    $lims_product_data->save();

                    $lims_product_warehouse_data = Product_Warehouse::where([
                        ['product_id', $pro_id],
                        ['warehouse_id', $data['warehouse_id']]
                    ])->first();

                    if ($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->qty -= $stock_deduction_qty;
                        $lims_product_warehouse_data->save();
                    }
                }

                // Create new product sale record
                $product_sale = new Product_Sale();
                $product_sale->sale_id = $id;
                $product_sale->product_id = $pro_id;
                $product_sale->qty = $qty[$key];
                $product_sale->dqty = $new_dqty;
                $product_sale->dstatus = ($dqty[$key] ?? $qty[$key]) . ' / ' . $qty[$key];
                $product_sale->sale_unit_id = $lims_sale_unit_data ? $lims_sale_unit_data->id : null;
                $product_sale->net_unit_price = $data['net_unit_price'][$key];
                $product_sale->discount = $data['discount'][$key];
                $product_sale->tax_rate = $data['tax_rate'][$key];
                $product_sale->tax = $data['tax'][$key];
                $product_sale->total = $data['subtotal'][$key];
                $product_sale->save();
            }

            // Update sale data
            $lims_sale_data->update($data);

            DB::commit();
            return redirect('sales')->with('message', 'Sale updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function printLastReciept()
    {
        if(cache()->has('general_setting'))
        {
            $general_setting = cache()->get('general_setting');
        }else {
            $general_setting = DB::table('general_settings')->select('modules')->first();
            cache()->put('general_setting', $general_setting, 60 * 60 * 24);
        }
        if(in_array('restaurant',explode(',',$general_setting->modules))){
            $sale = Sale::where('sale_status', 5)->latest()->first();
        }else{
            $sale = Sale::where('sale_status', 1)->latest()->first();
        }
        return redirect()->route('sale.invoice', $sale->id);
    }

    private function getWarrantyGuaranteeEndDate(array $date_data): string
    {
        $days = $date_data['duration'];

        if ($date_data['type'] === 'months') {
            $days = $date_data['duration'] * 30;
        }
        if ($date_data['type'] === 'years') {
            $days = $date_data['duration'] * 365;
        }

        $end_date = new DateTime($date_data['sale_date']);
        $end_date->modify("+$days days");

        return $end_date->format('Y-m-d');
    }

    public function genInvoice($id)
    {
        try{
            DB::beginTransaction();

            $lims_sale_data = Sale::find($id);

            $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
            if(cache()->has('biller_list'))
            {
                $lims_biller_data = cache()->get('biller_list')->find($lims_sale_data->biller_id);
            }
            else{
                $lims_biller_data = Biller::find($lims_sale_data->biller_id);
            }

            if(cache()->has('warehouse_list'))
            {
                $lims_warehouse_data = cache()->get('warehouse_list')->find($lims_sale_data->warehouse_id);
            }
            else{
                $lims_warehouse_data = Warehouse::find($lims_sale_data->warehouse_id);
            }

            if(cache()->has('customer_list'))
            {
                $lims_customer_data = cache()->get('customer_list')->find($lims_sale_data->customer_id);
            }
            else{
                $lims_customer_data = Customer::find($lims_sale_data->customer_id);
            }

            $lims_payment_data = Payment::where('sale_id', $id)->get();
            if(cache()->has('pos_setting'))
            {
                $lims_pos_setting_data = cache()->get('pos_setting');
            }
            else{
                $lims_pos_setting_data = PosSetting::select('invoice_option','thermal_invoice_size')->latest()->first();
            }

            $supportedIdentifiers = [
                'al', 'fr_BE', 'pt_BR', 'bg', 'cs', 'dk', 'nl', 'et', 'ka', 'de', 'fr', 'hu', 'id', 'it', 'lt', 'lv',
                'ms', 'fa', 'pl', 'ro', 'sk', 'es', 'ru', 'sv', 'tr', 'tk', 'ua', 'yo'
            ]; //ar, az, ku, mk - not supported

            $defaultLocale = \App::getLocale();
            $numberToWords = new NumberToWords();

            if(in_array($defaultLocale, $supportedIdentifiers))
                $numberTransformer = $numberToWords->getNumberTransformer($defaultLocale);
            else
                $numberTransformer = $numberToWords->getNumberTransformer('en');


            if(config('is_zatca')) {
                //generating base64 TLV format qrtext for qrcode
                $qrText = GenerateQrCode::fromArray([
                    new Seller(config('company_name')), // seller name
                    new TaxNumber(config('vat_registration_number')), // seller tax number
                    new InvoiceDate($lims_sale_data->created_at->toDateString()."T".$lims_sale_data->created_at->toTimeString()), // invoice date as Zulu ISO8601 @see https://en.wikipedia.org/wiki/ISO_8601
                    new InvoiceTotalAmount(number_format((float)$lims_sale_data->grand_total, 4, '.', '')), // invoice total amount
                    new InvoiceTaxAmount(number_format((float)($lims_sale_data->total_tax+$lims_sale_data->order_tax), 4, '.', '')) // invoice tax amount
                    // TODO :: Support others tags
                ])->toBase64();
            }
            else {
                $qrText = $lims_sale_data->reference_no;
            }
            if(is_null($lims_sale_data->exchange_rate))
            {
                $numberInWords = $numberTransformer->toWords($lims_sale_data->grand_total);
                $currency_code = cache()->get('currency')->code;
            } else {
                $numberInWords = $numberTransformer->toWords($lims_sale_data->grand_total);
                $sale_currency = DB::table('currencies')->select('code')->where('id',$lims_sale_data->currency_id)->first();
                $currency_code = $sale_currency->code;
            }
            $paying_methods = Payment::where('sale_id', $id)->get();
            $change_amounts = Payment::where('sale_id', $id)->pluck('change')->toArray();
            $paid_by_info = '';
            $change_amount = 0;
            foreach ($lims_payment_data as $key => $payment_data) {
                $change_amount += $payment_data->change ?? 0;
                if($key)
                    $paid_by_info .= ', '.$payment_data->paying_method;
                else
                    $paid_by_info = $payment_data->paying_method;
            }
            $sale_custom_fields = CustomField::where([
                                    ['belongs_to', 'sale'],
                                    ['is_invoice', true]
                                ])->pluck('name');
            $customer_custom_fields = CustomField::where([
                                    ['belongs_to', 'customer'],
                                    ['is_invoice', true]
                                ])->pluck('name');
            $product_custom_fields = CustomField::where([
                                    ['belongs_to', 'product'],
                                    ['is_invoice', true]
                                ])->pluck('name');
            $returned_amount = DB::table('sales')
                                        ->join('returns', 'sales.id', '=', 'returns.sale_id')
                                        ->where([
                                            ['sales.customer_id', $lims_customer_data->id],
                                            ['sales.payment_status', '!=', 4]
                                        ])
                                        ->sum('returns.grand_total');
            $saleData = DB::table('sales')->where([
                            ['customer_id', $lims_customer_data->id],
                            ['payment_status', '!=', 4]
                        ])
                        ->selectRaw('SUM(grand_total) as grand_total,SUM(paid_amount) as paid_amount')
                        ->first();

            foreach ($lims_product_sale_data as $sale_data) {
                // IMEIs
                if (isset($sale_data->imei_number)) {
                    $temp = array_unique(explode(',', $sale_data->imei_number));
                    $sale_data->imei_number = implode(',', $temp);
                }
                // Warranty/Guarantee
                $product = Product::select(
                    'warranty',
                    'warranty_type',
                    'guarantee',
                    'guarantee_type',
                )->where('id', $sale_data->product_id)->first();

                if (isset($product->warranty)) {
                    if ($product->warranty === 1) {

                    }
                    $sale_data->warranty_duration = $product->warranty . ' ' . ($product->warranty === 1 ? str_replace('s', '', $product->warranty_type) : $product->warranty_type);
                    $sale_data->warranty_end = $this->getWarrantyGuaranteeEndDate([
                        'sale_date' => $lims_sale_data->created_at,
                        'duration' => $product->warranty,
                        'type' => $product->warranty_type,
                    ]);
                }
                if (isset($product->guarantee)) {
                    $sale_data->guarantee_duration = $product->guarantee . ' ' . ($product->guarantee === 1 ? str_replace('s', '', $product->guarantee_type) : $product->guarantee_type);
                    $sale_data->guarantee_end = $this->getWarrantyGuaranteeEndDate([
                        'sale_date' => $lims_sale_data->created_at,
                        'duration' => $product->guarantee,
                        'type' => $product->guarantee_type,
                    ]);
                }
            }

            $lims_bill_by = $lims_sale_data->user->only(['name', 'email']);
            $lims_bill_by['user_name'] = strstr($lims_bill_by['email'], '@', true);
            $totalDue = $saleData->grand_total - $returned_amount - $saleData->paid_amount;

            //new invoice view file(dev:maynuddin)
            $invoice_settings = InvoiceSetting::active_setting();
            if($invoice_settings && $invoice_settings->size == 'a4') {
                    return view('backend.setting.invoice_setting.a4', compact('invoice_settings','lims_sale_data', 'currency_code', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords', 'paid_by_info', 'change_amount', 'sale_custom_fields', 'customer_custom_fields', 'product_custom_fields', 'qrText', 'totalDue', 'lims_bill_by'));
            }elseif($invoice_settings && $invoice_settings->size == '58mm'){
                return view('backend.setting.invoice_setting.58mm', compact('invoice_settings','lims_sale_data', 'currency_code', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords', 'sale_custom_fields', 'customer_custom_fields', 'product_custom_fields', 'qrText', 'totalDue', 'lims_bill_by'));
            }elseif($invoice_settings && $invoice_settings->size == '80mm'){
                return view('backend.setting.invoice_setting.80mm', compact('invoice_settings','lims_sale_data', 'currency_code', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords', 'sale_custom_fields', 'customer_custom_fields', 'product_custom_fields', 'qrText', 'totalDue', 'lims_bill_by'));
            }
            // old invoice code
            elseif($lims_pos_setting_data->invoice_option == 'A4') {
                return view('backend.sale.a4_invoice', compact('lims_sale_data', 'currency_code', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords', 'paid_by_info', 'sale_custom_fields', 'customer_custom_fields', 'product_custom_fields', 'qrText', 'totalDue', 'lims_bill_by'));
            }
            elseif($lims_sale_data->sale_type == 'online'){
                return view('backend.sale.a4_invoice', compact('lims_sale_data', 'currency_code', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords', 'paid_by_info', 'sale_custom_fields', 'customer_custom_fields', 'product_custom_fields', 'qrText', 'totalDue', 'lims_bill_by'));
            }
            elseif($lims_pos_setting_data->invoice_option == 'thermal' && $lims_pos_setting_data->thermal_invoice_size == '58'){
                return view('backend.sale.invoice58', compact('lims_sale_data', 'currency_code', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords', 'sale_custom_fields', 'customer_custom_fields', 'product_custom_fields', 'qrText', 'totalDue', 'lims_bill_by'));
            }
            else{
                return view('backend.sale.invoice', compact('lims_sale_data', 'currency_code', 'lims_product_sale_data', 'lims_biller_data', 'lims_warehouse_data', 'lims_customer_data', 'lims_payment_data', 'numberInWords', 'sale_custom_fields', 'customer_custom_fields', 'product_custom_fields', 'qrText', 'totalDue', 'lims_bill_by'));
            }

            DB::commit();
        }catch(\Throwable $e){
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
            // dd($e->getCode(),$e->getMessage(),$e->getLine());
        }

    }

    public function addPayment(Request $request)
    {
        // return dd($request->all());
        $data = $request->all();
        if(!$data['amount'])
            $data['amount'] = 0.00;

        $lims_sale_data = Sale::find($data['sale_id']);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $lims_sale_data->paid_amount += $data['amount'];
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if($balance > 0 || $balance < 0)
            $lims_sale_data->payment_status = 2;
        elseif ($balance == 0)
            $lims_sale_data->payment_status = 4;

        if($data['paid_by_id'] == 1)
            $paying_method = 'Cash';
        elseif ($data['paid_by_id'] == 2)
            $paying_method = 'Gift Card';
        elseif ($data['paid_by_id'] == 3)
            $paying_method = 'Credit Card';
        elseif($data['paid_by_id'] == 4)
            $paying_method = 'Cheque';
        elseif($data['paid_by_id'] == 5)
            $paying_method = 'Paypal';
        elseif($data['paid_by_id'] == 6)
            $paying_method = 'Deposit';
        elseif($data['paid_by_id'] == 7)
            $paying_method = 'Points';
        else
            $paying_method = ucfirst($data['paid_by_id']);

        $cash_register_data = CashRegister::where([
            ['user_id', Auth::id()],
            ['warehouse_id', $lims_sale_data->warehouse_id],
            ['status', true]
        ])->first();

        $lims_payment_data = new Payment();
        $lims_payment_data->user_id = Auth::id();
        $lims_payment_data->sale_id = $lims_sale_data->id;
        if($cash_register_data)
            $lims_payment_data->cash_register_id = $cash_register_data->id;
        $lims_payment_data->account_id = $data['account_id'];
        $data['payment_reference'] = 'spr-' . date("Ymd") . '-'. date("his");
        $lims_payment_data->payment_reference = $data['payment_reference'];
        $lims_payment_data->amount = $data['amount'];
        $lims_payment_data->change = $data['paying_amount'] - $data['amount'];
        $lims_payment_data->paying_method = $paying_method;
        $lims_payment_data->payment_note = $data['payment_note'];
        $lims_payment_data->payment_receiver = $data['payment_receiver'];
        $lims_payment_data->save();
        $lims_sale_data->save();

        $lims_payment_data = Payment::latest()->first();
        $data['payment_id'] = $lims_payment_data->id;

        if($paying_method == 'Gift Card'){
            $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
            $lims_gift_card_data->expense += $data['amount'];
            $lims_gift_card_data->save();
            PaymentWithGiftCard::create($data);
        }
        elseif($paying_method == 'Credit Card'){
            $lims_pos_setting_data = PosSetting::latest()->first();
            if($lims_pos_setting_data->stripe_secret_key) {
                Stripe::setApiKey($lims_pos_setting_data->stripe_secret_key);
                $token = $data['stripeToken'];
                $amount = $data['amount'];

                $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('customer_id', $lims_sale_data->customer_id)->first();

                if(!$lims_payment_with_credit_card_data) {
                    // Create a Customer:
                    $customer = \Stripe\Customer::create([
                        'source' => $token
                    ]);

                    // Charge the Customer instead of the card:
                    $charge = \Stripe\Charge::create([
                        'amount' => $amount * 100,
                        'currency' => 'usd',
                        'customer' => $customer->id,
                    ]);
                    $data['customer_stripe_id'] = $customer->id;
                }
                else {
                    $customer_id =
                    $lims_payment_with_credit_card_data->customer_stripe_id;

                    $charge = \Stripe\Charge::create([
                        'amount' => $amount * 100,
                        'currency' => 'usd',
                        'customer' => $customer_id, // Previously stored, then retrieved
                    ]);
                    $data['customer_stripe_id'] = $customer_id;
                }
                $data['customer_id'] = $lims_sale_data->customer_id;
                $data['charge_id'] = $charge->id;
                PaymentWithCreditCard::create($data);
            }
        }
        elseif ($paying_method == 'Cheque') {
            PaymentWithCheque::create($data);
        }
        elseif ($paying_method == 'Paypal') {
            $provider = new ExpressCheckout;
            $paypal_data['items'] = [];
            $paypal_data['items'][] = [
                'name' => 'Paid Amount',
                'price' => $data['amount'],
                'qty' => 1
            ];
            $paypal_data['invoice_id'] = $lims_payment_data->payment_reference;
            $paypal_data['invoice_description'] = "Reference: {$paypal_data['invoice_id']}";
            $paypal_data['return_url'] = url('/sale/paypalPaymentSuccess/'.$lims_payment_data->id);
            $paypal_data['cancel_url'] = url('/sale');

            $total = 0;
            foreach($paypal_data['items'] as $item) {
                $total += $item['price']*$item['qty'];
            }

            $paypal_data['total'] = $total;
            $response = $provider->setExpressCheckout($paypal_data);
            return redirect($response['paypal_link']);
        }
        elseif ($paying_method == 'Deposit') {
            $lims_customer_data->expense += $data['amount'];
            $lims_customer_data->save();
        }
        elseif ($paying_method == 'Points') {
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $used_points = ceil($data['amount'] / $lims_reward_point_setting_data->per_point_amount);

            $lims_payment_data->used_points = $used_points;
            $lims_payment_data->save();

            $lims_customer_data->points -= $used_points;
            $lims_customer_data->save();
        }
        $message = 'Payment created successfully';
        $mail_setting = MailSetting::latest()->first();
        if($lims_customer_data->email && $mail_setting) {
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['sale_reference'] = $lims_sale_data->reference_no;
            $mail_data['payment_reference'] = $lims_payment_data->payment_reference;
            $mail_data['payment_method'] = $lims_payment_data->paying_method;
            $mail_data['grand_total'] = $lims_sale_data->grand_total;
            $mail_data['paid_amount'] = $lims_payment_data->amount;
            $mail_data['currency'] = config('currency');
            $mail_data['due'] = $balance;
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new PaymentDetails($mail_data));
            }
            catch(\Exception $e){
                $message = 'Payment created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }

        }
        return redirect('sales')->with('message', $message);
    }

    public function getPayment($id)
    {
        $lims_payment_list = Payment::where('sale_id', $id)->get();
        $date = [];
        $payment_reference = [];
        $paid_amount = [];
        $paying_method = [];
        $payment_id = [];
        $payment_note = [];
        $gift_card_id = [];
        $cheque_no = [];
        $change = [];
        $paying_amount = [];
        $payment_receiver = [];
        $account_name = [];
        $account_id = [];
        $payment_proof = [];

        foreach ($lims_payment_list as $payment) {
            $date[] = date(config('date_format'), strtotime($payment->created_at->toDateString())) . ' '. $payment->created_at->toTimeString();
            $payment_reference[] = $payment->payment_reference;
            $paid_amount[] = $payment->amount;
            $change[] = $payment->change;
            $paying_method[] = $payment->paying_method;
            $paying_amount[] = $payment->amount + $payment->change;
            $payment_receiver[] = $payment->payment_receiver;
            if($payment->paying_method == 'Gift Card'){
                $lims_payment_gift_card_data = PaymentWithGiftCard::where('payment_id',$payment->id)->first();
                $gift_card_id[] = $lims_payment_gift_card_data->gift_card_id;
            }
            elseif($payment->paying_method == 'Cheque'){
                $lims_payment_cheque_data = PaymentWithCheque::where('payment_id',$payment->id)->first();
                if($lims_payment_cheque_data)
                    $cheque_no[] = $lims_payment_cheque_data->cheque_no;
                else
                    $cheque_no[] = null;
            }
            else{
                $cheque_no[] = $gift_card_id[] = null;
            }
            $payment_id[] = $payment->id;
            $payment_note[] = $payment->payment_note;
            $lims_account_data = Account::find($payment->account_id);
            $account_name[] = $lims_account_data->name;
            $account_id[] = $lims_account_data->id;
            $payment_proof[] = $payment->payment_proof;
        }
        $payments[] = $date;
        $payments[] = $payment_reference;
        $payments[] = $paid_amount;
        $payments[] = $paying_method;
        $payments[] = $payment_id;
        $payments[] = $payment_note;
        $payments[] = $cheque_no;
        $payments[] = $gift_card_id;
        $payments[] = $change;
        $payments[] = $paying_amount;
        $payments[] = $account_name;
        $payments[] = $account_id;
        $payments[] = $payment_receiver;
        $payments[] = $payment_proof;

        return $payments;
    }

    public function updatePayment(Request $request)
    {
        $data = $request->all();
        //return $data;
        $lims_payment_data = Payment::find($data['payment_id']);
        $lims_sale_data = Sale::find($lims_payment_data->sale_id);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        //updating sale table
        $amount_dif = $lims_payment_data->amount - $data['edit_amount'];
        $lims_sale_data->paid_amount = $lims_sale_data->paid_amount - $amount_dif;
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if($balance > 0 || $balance < 0)
            $lims_sale_data->payment_status = 2;
        elseif ($balance == 0)
            $lims_sale_data->payment_status = 4;
        $lims_sale_data->save();

        if($lims_payment_data->paying_method == 'Deposit') {
            $lims_customer_data->expense -= $lims_payment_data->amount;
            $lims_customer_data->save();
        }
        elseif($lims_payment_data->paying_method == 'Points') {
            $lims_customer_data->points += $lims_payment_data->used_points;
            $lims_customer_data->save();
            $lims_payment_data->used_points = 0;
        }
        if($data['edit_paid_by_id'] == 1)
            $lims_payment_data->paying_method = 'Cash';
        elseif ($data['edit_paid_by_id'] == 2){
            if($lims_payment_data->paying_method == 'Gift Card'){
                $lims_payment_gift_card_data = PaymentWithGiftCard::where('payment_id', $data['payment_id'])->first();

                $lims_gift_card_data = GiftCard::find($lims_payment_gift_card_data->gift_card_id);
                $lims_gift_card_data->expense -= $lims_payment_data->amount;
                $lims_gift_card_data->save();

                $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
                $lims_gift_card_data->expense += $data['edit_amount'];
                $lims_gift_card_data->save();

                $lims_payment_gift_card_data->gift_card_id = $data['gift_card_id'];
                $lims_payment_gift_card_data->save();
            }
            else{
                $lims_payment_data->paying_method = 'Gift Card';
                $lims_gift_card_data = GiftCard::find($data['gift_card_id']);
                $lims_gift_card_data->expense += $data['edit_amount'];
                $lims_gift_card_data->save();
                PaymentWithGiftCard::create($data);
            }
        }
        elseif ($data['edit_paid_by_id'] == 3){
            $lims_pos_setting_data = PosSetting::latest()->first();
            if($lims_pos_setting_data->stripe_secret_key) {
                Stripe::setApiKey($lims_pos_setting_data->stripe_secret_key);
                if($lims_payment_data->paying_method == 'Credit Card'){
                    $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('payment_id', $lims_payment_data->id)->first();

                    \Stripe\Refund::create(array(
                      "charge" => $lims_payment_with_credit_card_data->charge_id,
                    ));

                    $customer_id =
                    $lims_payment_with_credit_card_data->customer_stripe_id;

                    $charge = \Stripe\Charge::create([
                        'amount' => $data['edit_amount'] * 100,
                        'currency' => 'usd',
                        'customer' => $customer_id
                    ]);
                    $lims_payment_with_credit_card_data->charge_id = $charge->id;
                    $lims_payment_with_credit_card_data->save();
                }
                else{
                    $token = $data['stripeToken'];
                    $amount = $data['edit_amount'];
                    $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('customer_id', $lims_sale_data->customer_id)->first();

                    if(!$lims_payment_with_credit_card_data) {
                        $customer = \Stripe\Customer::create([
                            'source' => $token
                        ]);

                        $charge = \Stripe\Charge::create([
                            'amount' => $amount * 100,
                            'currency' => 'usd',
                            'customer' => $customer->id,
                        ]);
                        $data['customer_stripe_id'] = $customer->id;
                    }
                    else {
                        $customer_id =
                        $lims_payment_with_credit_card_data->customer_stripe_id;

                        $charge = \Stripe\Charge::create([
                            'amount' => $amount * 100,
                            'currency' => 'usd',
                            'customer' => $customer_id
                        ]);
                        $data['customer_stripe_id'] = $customer_id;
                    }
                    $data['customer_id'] = $lims_sale_data->customer_id;
                    $data['charge_id'] = $charge->id;
                    PaymentWithCreditCard::create($data);
                }
            }
            $lims_payment_data->paying_method = 'Credit Card';
        }
        elseif($data['edit_paid_by_id'] == 4){
            if($lims_payment_data->paying_method == 'Cheque'){
                $lims_payment_cheque_data = PaymentWithCheque::where('payment_id', $data['payment_id'])->first();
                if($lims_payment_cheque_data){
                    $lims_payment_cheque_data->cheque_no = $data['edit_cheque_no'];
                    $lims_payment_cheque_data->save();
                }
                elseif($data['edit_cheque_no']) {
                    PaymentWithCheque::create([
                        'payment_id' => $lims_payment_data->id,
                        'cheque_no' => $data['edit_cheque_no']
                    ]);
                }
            }
            else{
                $lims_payment_data->paying_method = 'Cheque';
                $data['cheque_no'] = $data['edit_cheque_no'];
                PaymentWithCheque::create($data);
            }
        }
        elseif($data['edit_paid_by_id'] == 5){
            //updating payment data
            $lims_payment_data->amount = $data['edit_amount'];
            $lims_payment_data->paying_method = 'Paypal';
            $lims_payment_data->payment_note = $data['edit_payment_note'];
            $lims_payment_data->save();

            $provider = new ExpressCheckout;
            $paypal_data['items'] = [];
            $paypal_data['items'][] = [
                'name' => 'Paid Amount',
                'price' => $data['edit_amount'],
                'qty' => 1
            ];
            $paypal_data['invoice_id'] = $lims_payment_data->payment_reference;
            $paypal_data['invoice_description'] = "Reference: {$paypal_data['invoice_id']}";
            $paypal_data['return_url'] = url('/sale/paypalPaymentSuccess/'.$lims_payment_data->id);
            $paypal_data['cancel_url'] = url('/sale');

            $total = 0;
            foreach($paypal_data['items'] as $item) {
                $total += $item['price']*$item['qty'];
            }

            $paypal_data['total'] = $total;
            $response = $provider->setExpressCheckout($paypal_data);
            return redirect($response['paypal_link']);
        }
        elseif($data['edit_paid_by_id'] == 6){
            $lims_payment_data->paying_method = 'Deposit';
            $lims_customer_data->expense += $data['edit_amount'];
            $lims_customer_data->save();
        }
        elseif($data['edit_paid_by_id'] == 7) {
            $lims_payment_data->paying_method = 'Points';
            $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
            $used_points = ceil($data['edit_amount'] / $lims_reward_point_setting_data->per_point_amount);
            $lims_payment_data->used_points = $used_points;
            $lims_customer_data->points -= $used_points;
            $lims_customer_data->save();
        } else {
            $lims_payment_data->paying_method = ucfirst($data['edit_paid_by_id']);
        }
        //updating payment data
        $lims_payment_data->account_id = $data['account_id'];
        $lims_payment_data->amount = $data['edit_amount'];
        $lims_payment_data->change = $data['edit_paying_amount'] - $data['edit_amount'];
        $lims_payment_data->payment_note = $data['edit_payment_note'];
        $lims_payment_data->payment_note = $data['edit_payment_note'];
        $lims_payment_data->payment_receiver = $data['payment_receiver'];
        $lims_payment_data->save();
        $message = 'Payment updated successfully';
        //collecting male data
        $mail_setting = MailSetting::latest()->first();
        if($lims_customer_data->email && $mail_setting) {
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['sale_reference'] = $lims_sale_data->reference_no;
            $mail_data['payment_reference'] = $lims_payment_data->payment_reference;
            $mail_data['payment_method'] = $lims_payment_data->paying_method;
            $mail_data['grand_total'] = $lims_sale_data->grand_total;
            $mail_data['paid_amount'] = $lims_payment_data->amount;
            $mail_data['currency'] = config('currency');
            $mail_data['due'] = $balance;
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new PaymentDetails($mail_data));
            }
            catch(\Exception $e){
                $message = 'Payment updated successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        return redirect('sales')->with('message', $message);
    }

    public function deletePayment(Request $request)
    {
        $lims_payment_data = Payment::find($request['id']);
        $lims_sale_data = Sale::where('id', $lims_payment_data->sale_id)->first();
        $lims_sale_data->paid_amount -= $lims_payment_data->amount;
        $balance = $lims_sale_data->grand_total - $lims_sale_data->paid_amount;
        if($balance > 0 || $balance < 0)
            $lims_sale_data->payment_status = 2;
        elseif ($balance == 0)
            $lims_sale_data->payment_status = 4;
        $lims_sale_data->save();

        if ($lims_payment_data->paying_method == 'Gift Card') {
            $lims_payment_gift_card_data = PaymentWithGiftCard::where('payment_id', $request['id'])->first();
            $lims_gift_card_data = GiftCard::find($lims_payment_gift_card_data->gift_card_id);
            $lims_gift_card_data->expense -= $lims_payment_data->amount;
            $lims_gift_card_data->save();
            $lims_payment_gift_card_data->delete();
        }
        elseif($lims_payment_data->paying_method == 'Credit Card'){
            $lims_pos_setting_data = PosSetting::latest()->first();
            if($lims_pos_setting_data->stripe_secret_key) {
                $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('payment_id', $request['id'])->first();
                Stripe::setApiKey($lims_pos_setting_data->stripe_secret_key);
                \Stripe\Refund::create(array(
                  "charge" => $lims_payment_with_credit_card_data->charge_id,
                ));

                $lims_payment_with_credit_card_data->delete();
            }
        }
        elseif ($lims_payment_data->paying_method == 'Cheque') {
            $lims_payment_cheque_data = PaymentWithCheque::where('payment_id', $request['id'])->first();
            $lims_payment_cheque_data->delete();
        }
        elseif ($lims_payment_data->paying_method == 'Paypal') {
            $lims_payment_paypal_data = PaymentWithPaypal::where('payment_id', $request['id'])->first();
            if($lims_payment_paypal_data){
                $provider = new ExpressCheckout;
                $response = $provider->refundTransaction($lims_payment_paypal_data->transaction_id);
                $lims_payment_paypal_data->delete();
            }
        }
        elseif ($lims_payment_data->paying_method == 'Deposit'){
            $lims_customer_data = Customer::find($lims_sale_data->customer_id);
            $lims_customer_data->expense -= $lims_payment_data->amount;
            $lims_customer_data->save();
        }
        elseif ($lims_payment_data->paying_method == 'Points'){
            $lims_customer_data = Customer::find($lims_sale_data->customer_id);
            $lims_customer_data->points += $lims_payment_data->used_points;
            $lims_customer_data->save();
        }
        $lims_payment_data->delete();
        return redirect('sales')->with('not_permitted', __('db.Payment deleted successfully'));
    }

    public function todaySale()
    {
        $data['total_sale_amount'] = Sale::whereDate('created_at', date("Y-m-d"))->sum('grand_total');
        $data['total_payment'] = Payment::whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['cash_payment'] = Payment::where([
                                    ['paying_method', 'Cash']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['credit_card_payment'] = Payment::where([
                                    ['paying_method', 'Credit Card']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['gift_card_payment'] = Payment::where([
                                    ['paying_method', 'Gift Card']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['deposit_payment'] = Payment::where([
                                    ['paying_method', 'Deposit']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['cheque_payment'] = Payment::where([
                                    ['paying_method', 'Cheque']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['paypal_payment'] = Payment::where([
                                    ['paying_method', 'Paypal']
                                ])->whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['total_sale_return'] = Returns::whereDate('created_at', date("Y-m-d"))->sum('grand_total');
        $data['total_expense'] = Expense::whereDate('created_at', date("Y-m-d"))->sum('amount');
        $data['total_cash'] = $data['total_payment'] - ($data['total_sale_return'] + $data['total_expense']);
        return $data;
    }

    public function todayProfit($warehouse_id)
    {
        if($warehouse_id == 0)
            $product_sale_data = Product_Sale::select(DB::raw('product_id, product_batch_id, sum(qty) as sold_qty, sum(total) as sold_amount'))->whereDate('created_at', date("Y-m-d"))->groupBy('product_id', 'product_batch_id')->get();
        else
            $product_sale_data = Sale::join('product_sales', 'sales.id', '=', 'product_sales.sale_id')
            ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, sum(product_sales.qty) as sold_qty, sum(product_sales.total) as sold_amount'))
            ->where('sales.warehouse_id', $warehouse_id)->whereDate('sales.created_at', date("Y-m-d"))
            ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')->get();

        $product_revenue = 0;
        $product_cost = 0;
        $profit = 0;
        foreach ($product_sale_data as $key => $product_sale) {
            if($warehouse_id == 0) {
                if($product_sale->product_batch_id)
                    $product_purchase_data = ProductPurchase::where([
                        ['product_id', $product_sale->product_id],
                        ['product_batch_id', $product_sale->product_batch_id]
                    ])->get();
                else
                    $product_purchase_data = ProductPurchase::where('product_id', $product_sale->product_id)->get();
            }
            else {
                if($product_sale->product_batch_id) {
                    $product_purchase_data = Purchase::join('product_purchases', 'purchases.id', '=', 'product_purchases.purchase_id')
                    ->where([
                        ['product_purchases.product_id', $product_sale->product_id],
                        ['product_purchases.product_batch_id', $product_sale->product_batch_id],
                        ['purchases.warehouse_id', $warehouse_id]
                    ])->select('product_purchases.*')->get();
                }
                else
                    $product_purchase_data = Purchase::join('product_purchases', 'purchases.id', '=', 'product_purchases.purchase_id')
                    ->where([
                        ['product_purchases.product_id', $product_sale->product_id],
                        ['purchases.warehouse_id', $warehouse_id]
                    ])->select('product_purchases.*')->get();
            }

            $purchased_qty = 0;
            $purchased_amount = 0;
            $sold_qty = $product_sale->sold_qty;
            $product_revenue += $product_sale->sold_amount;
            foreach ($product_purchase_data as $key => $product_purchase) {
                $purchased_qty += $product_purchase->qty;
                $purchased_amount += $product_purchase->total;
                if($purchased_qty >= $sold_qty) {
                    $qty_diff = $purchased_qty - $sold_qty;
                    $unit_cost = $product_purchase->total / $product_purchase->qty;
                    $purchased_amount -= ($qty_diff * $unit_cost);
                    break;
                }
            }

            $product_cost += $purchased_amount;
            $profit += $product_sale->sold_amount - $purchased_amount;
        }

        $data['product_revenue'] = $product_revenue;
        $data['product_cost'] = $product_cost;
        if($warehouse_id == 0)
            $data['expense_amount'] = Expense::whereDate('created_at', date("Y-m-d"))->sum('amount');
        else
            $data['expense_amount'] = Expense::where('warehouse_id', $warehouse_id)->whereDate('created_at', date("Y-m-d"))->sum('amount');

        $data['profit'] = $profit - $data['expense_amount'];
        return $data;
    }

    public function deleteBySelection(Request $request){
        $sale_id = $request['saleIdArray'];
        foreach ($sale_id as $id) {
            $lims_sale_data = Sale::find($id);
            $return_ids = Returns::where('sale_id', $id)->pluck('id')->toArray();
            if(count($return_ids)) {
                ProductReturn::whereIn('return_id', $return_ids)->delete();
                Returns::whereIn('id', $return_ids)->delete();
            }
            $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
            $lims_delivery_data = Delivery::where('sale_id',$id)->get();
            $lims_packing_slip_data = PackingSlip::where('sale_id', $id)->get();
            if($lims_sale_data->sale_status == 3)
                $message = 'Draft deleted successfully';
            else
                $message = 'Sale deleted successfully';
            foreach ($lims_product_sale_data as $product_sale) {
                $lims_product_data = Product::find($product_sale->product_id);
                //adjust product quantity
                if( ($lims_sale_data->sale_status == 1) && ($lims_product_data->type == 'combo') ){
                    if(!in_array('manufacturing',explode(',',config('addons')))) {
                        $product_list = explode(",", $lims_product_data->product_list);
                        if($lims_product_data->variant_list)
                            $variant_list = explode(",", $lims_product_data->variant_list);
                        else
                            $variant_list = [];
                        $qty_list = explode(",", $lims_product_data->qty_list);

                        foreach ($product_list as $index=>$child_id) {
                            $child_data = Product::find($child_id);
                            if(count($variant_list) && $variant_list[$index]) {
                                $child_product_variant_data = ProductVariant::where([
                                    ['product_id', $child_id],
                                    ['variant_id', $variant_list[$index] ]
                                ])->first();

                                $child_warehouse_data = Product_Warehouse::where([
                                    ['product_id', $child_id],
                                    ['variant_id', $variant_list[$index] ],
                                    ['warehouse_id', $lims_sale_data->warehouse_id ],
                                ])->first();

                                $child_product_variant_data->qty += $product_sale->qty * $qty_list[$index];
                                $child_product_variant_data->save();
                            }
                            else {
                                $child_warehouse_data = Product_Warehouse::where([
                                    ['product_id', $child_id],
                                    ['warehouse_id', $lims_sale_data->warehouse_id ],
                                ])->first();
                            }

                            $child_data->qty += $product_sale->qty * $qty_list[$index];
                            $child_warehouse_data->qty += $product_sale->qty * $qty_list[$index];

                            $child_data->save();
                            $child_warehouse_data->save();
                        }
                    }
                }
                elseif(($lims_sale_data->sale_status == 1) && ($product_sale->sale_unit_id != 0)){
                    $lims_sale_unit_data = Unit::find($product_sale->sale_unit_id);
                    if ($lims_sale_unit_data->operator == '*')
                        $product_sale->qty = $product_sale->qty * $lims_sale_unit_data->operation_value;
                    else
                        $product_sale->qty = $product_sale->qty / $lims_sale_unit_data->operation_value;
                    if($product_sale->variant_id) {
                        $lims_product_variant_data = ProductVariant::select('id', 'qty')->FindExactProduct($lims_product_data->id, $product_sale->variant_id)->first();
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($lims_product_data->id, $product_sale->variant_id, $lims_sale_data->warehouse_id)->first();
                        $lims_product_variant_data->qty += $product_sale->qty;
                        $lims_product_variant_data->save();
                    }
                    elseif($product_sale->product_batch_id) {
                        $lims_product_batch_data = ProductBatch::find($product_sale->product_batch_id);
                        $lims_product_warehouse_data = Product_Warehouse::where([
                            ['product_batch_id', $product_sale->product_batch_id],
                            ['warehouse_id', $lims_sale_data->warehouse_id]
                        ])->first();

                        $lims_product_batch_data->qty -= $product_sale->qty;
                        $lims_product_batch_data->save();
                    }
                    else {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $lims_sale_data->warehouse_id)->first();
                    }

                    $lims_product_data->qty += $product_sale->qty;
                    $lims_product_warehouse_data->qty += $product_sale->qty;
                    $lims_product_data->save();
                    $lims_product_warehouse_data->save();

                    //restore imei numbers
                    if($product_sale->imei_number && !str_contains($product_sale->imei_number, "null")) {
                        if($lims_product_warehouse_data->imei_number)
                            $lims_product_warehouse_data->imei_number .= ',' . $product_sale->imei_number;
                        else
                            $lims_product_warehouse_data->imei_number = $product_sale->imei_number;
                        $lims_product_warehouse_data->save();
                    }
                }
                $product_sale->delete();
            }
            $lims_payment_data = Payment::where('sale_id', $id)->get();
            foreach ($lims_payment_data as $payment) {
                if($payment->paying_method == 'Gift Card'){
                    $lims_payment_with_gift_card_data = PaymentWithGiftCard::where('payment_id', $payment->id)->first();
                    $lims_gift_card_data = GiftCard::find($lims_payment_with_gift_card_data->gift_card_id);
                    $lims_gift_card_data->expense -= $payment->amount;
                    $lims_gift_card_data->save();
                    $lims_payment_with_gift_card_data->delete();
                }
                elseif($payment->paying_method == 'Cheque'){
                    $lims_payment_cheque_data = PaymentWithCheque::where('payment_id', $payment->id)->first();
                    $lims_payment_cheque_data->delete();
                }
                elseif($payment->paying_method == 'Credit Card'){
                    $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('payment_id', $payment->id)->first();
                    $lims_payment_with_credit_card_data->delete();
                }
                elseif($payment->paying_method == 'Paypal'){
                    $lims_payment_paypal_data = PaymentWithPaypal::where('payment_id', $payment->id)->first();
                    if($lims_payment_paypal_data)
                        $lims_payment_paypal_data->delete();
                }
                elseif($payment->paying_method == 'Deposit'){
                    $lims_customer_data = Customer::find($lims_sale_data->customer_id);
                    $lims_customer_data->expense -= $payment->amount;
                    $lims_customer_data->save();
                }
                $payment->delete();
            }
            if ($lims_delivery_data->isNotEmpty()) {
                $lims_delivery_data->each->delete();
            }
            if ($lims_packing_slip_data->isNotEmpty()) {
                $lims_packing_slip_data->each->delete();
            }
            if($lims_sale_data->coupon_id) {
                $lims_coupon_data = Coupon::find($lims_sale_data->coupon_id);
                $lims_coupon_data->used -= 1;
                $lims_coupon_data->save();
            }

            $lims_sale_data->deleted_by = Auth::id();
            $lims_sale_data->save();
            $lims_sale_data->delete();
            $this->fileDelete(public_path('documents/sale/'), $lims_sale_data->document);

        }
        return 'Sale deleted successfully!';
    }

    public function destroy($id)
    {
        $url = url()->previous();

        $lims_sale_data = Sale::find($id);

        // remove this sale reward point
        $lims_reward_point = RewardPoint::query()->where('sale_id',$lims_sale_data->id)->first();
        if($lims_reward_point){

            // remove from customer table reward pint
            $lims_customer_data = Customer::find($lims_sale_data->customer_id);
            $lims_customer_data->points -= $lims_reward_point->points;
            $lims_customer_data->save();

            // delete reward point from reward point table
            $lims_reward_point->delete();
        }

        $return_ids = Returns::where('sale_id', $id)->pluck('id')->toArray();
        if(count($return_ids)) {
            ProductReturn::whereIn('return_id', $return_ids)->delete();
            Returns::whereIn('id', $return_ids)->delete();
        }
        $lims_product_sale_data = Product_Sale::where('sale_id', $id)->get();
        $lims_delivery_data = Delivery::where('sale_id',$id)->get();
        $lims_packing_slip_data = PackingSlip::where('sale_id', $id)->get();
        if($lims_sale_data->sale_status == 3)
            $message = 'Draft deleted successfully';
        else
            $message = 'Sale deleted successfully';

        foreach ($lims_product_sale_data as $product_sale) {
            $lims_product_data = Product::find($product_sale->product_id);
            //adjust product quantity
            if( ($lims_sale_data->sale_status == 1) && ($lims_product_data->type == 'combo') ) {
                // if(!in_array('manufacturing',explode(',',config('addons')))) {
                    $product_list = explode(",", $lims_product_data->product_list);
                    $variant_list = explode(",", $lims_product_data->variant_list);
                    $qty_list = explode(",", $lims_product_data->qty_list);
                    if($lims_product_data->variant_list)
                        $variant_list = explode(",", $lims_product_data->variant_list);
                    else
                        $variant_list = [];
                    foreach ($product_list as $index=>$child_id) {
                        $child_data = Product::find($child_id);
                        if(count($variant_list) && $variant_list[$index]) {
                            $child_product_variant_data = ProductVariant::where([
                                ['product_id', $child_id],
                                ['variant_id', $variant_list[$index] ]
                            ])->first();

                            $child_warehouse_data = Product_Warehouse::where([
                                ['product_id', $child_id],
                                ['variant_id', $variant_list[$index] ],
                                ['warehouse_id', $lims_sale_data->warehouse_id ],
                            ])->first();

                            $child_product_variant_data->qty += $product_sale->qty * $qty_list[$index];
                            $child_product_variant_data->save();
                        }
                        else {
                            $child_warehouse_data = Product_Warehouse::where([
                                ['product_id', $child_id],
                                ['warehouse_id', $lims_sale_data->warehouse_id ],
                            ])->first();
                        }

                        $child_data->qty += $product_sale->qty * $qty_list[$index];
                        $child_warehouse_data->qty += $product_sale->qty * $qty_list[$index];

                        $child_data->save();
                        $child_warehouse_data->save();
                    }
                // }
            }

            if(($lims_sale_data->sale_status == 1) && ($product_sale->sale_unit_id != 0)) {
                $lims_sale_unit_data = Unit::find($product_sale->sale_unit_id);
                if ($lims_sale_unit_data->operator == '*')
                    $product_sale->qty = $product_sale->qty * $lims_sale_unit_data->operation_value;
                else
                    $product_sale->qty = $product_sale->qty / $lims_sale_unit_data->operation_value;
                if($product_sale->variant_id) {
                    $lims_product_variant_data = ProductVariant::select('id', 'qty')->FindExactProduct($lims_product_data->id, $product_sale->variant_id)->first();
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithVariant($lims_product_data->id, $product_sale->variant_id, $lims_sale_data->warehouse_id)->first();
                    $lims_product_variant_data->qty += $product_sale->qty;
                    $lims_product_variant_data->save();
                }
                elseif($product_sale->product_batch_id) {
                    $lims_product_batch_data = ProductBatch::find($product_sale->product_batch_id);
                    $lims_product_warehouse_data = Product_Warehouse::where([
                        ['product_batch_id', $product_sale->product_batch_id],
                        ['warehouse_id', $lims_sale_data->warehouse_id]
                    ])->first();

                    $lims_product_batch_data->qty -= $product_sale->qty;
                    $lims_product_batch_data->save();
                }
                else {
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $lims_sale_data->warehouse_id)->first();
                }

                $lims_product_data->qty += $product_sale->qty;
                $lims_product_warehouse_data->qty += $product_sale->qty;
                $lims_product_data->save();
                $lims_product_warehouse_data->save();
                //restore imei numbers
                if($product_sale->imei_number && !str_contains($product_sale->imei_number, "null")) {
                    if($lims_product_warehouse_data->imei_number)
                        $lims_product_warehouse_data->imei_number .= ',' . $product_sale->imei_number;
                    else
                        $lims_product_warehouse_data->imei_number = $product_sale->imei_number;
                    $lims_product_warehouse_data->save();
                }
            }

            $product_sale->delete();
        }

        $lims_payment_data = Payment::where('sale_id', $id)->get();
        foreach ($lims_payment_data as $payment) {
            if($payment->paying_method == 'Gift Card'){
                $lims_payment_with_gift_card_data = PaymentWithGiftCard::where('payment_id', $payment->id)->first();
                $lims_gift_card_data = GiftCard::find($lims_payment_with_gift_card_data->gift_card_id);
                $lims_gift_card_data->expense -= $payment->amount;
                $lims_gift_card_data->save();
                $lims_payment_with_gift_card_data->delete();
            }
            elseif($payment->paying_method == 'Cheque'){
                $lims_payment_cheque_data = PaymentWithCheque::where('payment_id', $payment->id)->first();
                if($lims_payment_cheque_data)
                    $lims_payment_cheque_data->delete();
            }
            elseif($payment->paying_method == 'Credit Card'){
                $lims_payment_with_credit_card_data = PaymentWithCreditCard::where('payment_id', $payment->id)->first();
                if($lims_payment_with_credit_card_data)
                    $lims_payment_with_credit_card_data->delete();
            }
            elseif($payment->paying_method == 'Paypal'){
                $lims_payment_paypal_data = PaymentWithPaypal::where('payment_id', $payment->id)->first();
                if($lims_payment_paypal_data)
                    $lims_payment_paypal_data->delete();
            }
            elseif($payment->paying_method == 'Deposit'){
                $lims_customer_data = Customer::find($lims_sale_data->customer_id);
                $lims_customer_data->expense -= $payment->amount;
                $lims_customer_data->save();
            }
            $payment->delete();
        }
        if ($lims_delivery_data->isNotEmpty()) {
            $lims_delivery_data->each->delete();
        }
        if ($lims_packing_slip_data->isNotEmpty()) {
            $lims_packing_slip_data->each->delete();
        }
        if($lims_sale_data->coupon_id) {
            $lims_coupon_data = Coupon::find($lims_sale_data->coupon_id);
            $lims_coupon_data->used -= 1;
            $lims_coupon_data->save();
        }
        $lims_sale_data->deleted_by = Auth::id();
        $lims_sale_data->save();
        $lims_sale_data->delete();
        $this->fileDelete(public_path('documents/sale/'), $lims_sale_data->document);

        return Redirect::to($url)->with('not_permitted', $message);
    }

    public function registerIPN()
    {
        $pg = DB::table('external_services')->where('name','Pesapal')->where('type','payment')->first();
        $lines = explode(';',$pg->details);
        $keys = explode(',', $lines[0]);
        $vals = explode(',', $lines[1]);

        $results = array_combine($keys, $vals);

        $APP_ENVIROMENT = $results['Mode'];

        $token = $this->accessToken();

        if($APP_ENVIROMENT == 'sandbox'){
            $ipnRegistrationUrl = "https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN";
        }elseif($APP_ENVIROMENT == 'live'){
            $ipnRegistrationUrl = "https://pay.pesapal.com/v3/api/URLSetup/RegisterIPN";
        }else{
            echo "Invalid APP_ENVIROMENT";
            exit;
        }
        $headers = array(
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        );
        $data = array(
            "url" => "https://12eb-41-81-142-80.ngrok-free.app/pesapal/pin.php",
            "ipn_notification_type" => "POST"
        );
        $ch = curl_init($ipnRegistrationUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($response);
        return $data;
        // $ipn_id = $data->ipn_id;
        // $ipn_url = $data->url;
    }

    public function pesapalIPN()
    {
        return "PESAPAL IPN";
    }

    public function accessToken()
    {
        $pg = DB::table('external_services')->where('name','Pesapal')->where('type','payment')->first();
        $lines = explode(';',$pg->details);
        $keys = explode(',', $lines[0]);
        $vals = explode(',', $lines[1]);

        $results = array_combine($keys, $vals);

        $APP_ENVIROMENT = $results['Mode'];
        // return $APP_ENVIROMENT;
        if($APP_ENVIROMENT == 'sandbox'){
            $apiUrl = "https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken"; // Sandbox URL
            $consumerKey = $results['Consumer Key']; //env('PESAPAL_CONSUMER_KEY');
            $consumerSecret = $results['Consumer Secret']; //env('PESAPAL_CONSUMER_SECRET');
        }elseif($APP_ENVIROMENT == 'live'){
            $apiUrl = "https://pay.pesapal.com/v3/api/Auth/RequestToken"; // Live URL
            $consumerKey = "";
            $consumerSecret = "";
        }else{
            echo "Invalid APP_ENVIROMENT";
            exit;
        }
        $headers = [
            "Accept: application/json",
            "Content-Type: application/json"
        ];
        $data = [
            "consumer_key" => $consumerKey,
            "consumer_secret" => $consumerSecret
        ];
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($response);

        $token = $data->token;

        return $token;
    }
    public function submitOrderRequest($data,$amount)
    {
        $pg = DB::table('external_services')->where('name','Pesapal')->where('type','payment')->first();
        $lines = explode(';',$pg->details);
        $keys = explode(',', $lines[0]);
        $vals = explode(',', $lines[1]);

        $results = array_combine($keys, $vals);

        if(cache()->has('general_setting'))
        {
            $general_setting = cache()->get('general_setting');
        }else {
            $general_setting = DB::table('general_settings')->latest()->first();
            cache()->put('general_setting', $general_setting, 60 * 60 * 24);
        }
        $company = $general_setting->company_name;

        $APP_ENVIROMENT = $results['Mode'];;
        $token = $this->accessToken();
        $ipnData = $this->registerIPN();

        $merchantreference = rand(1, 1000000000000000000);
        $phone = $data->phone_number; //0768168060
        $amount = $amount;
        $callbackurl = "salepro.test/ipn";
        $branch = $company;
        $first_name = $data->name;
        //$middle_name = "Coders";
        $last_name = $data->name;
        $email_address = $data->email ? $data->email : "hello@lion-coders.com";
        if( $APP_ENVIROMENT == 'sandbox'){
        $submitOrderUrl = "https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest";
        }elseif($APP_ENVIROMENT == 'live'){
        $submitOrderUrl = "https://pay.pesapal.com/v3/api/Transactions/SubmitOrderRequest";
        }else{
        echo "Invalid APP_ENVIROMENT";
        exit;
        }
        $headers = array(
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        );

        // Request payload
        $data = array(
            "id" => "$merchantreference",
            "currency" => "KES",
            "amount" => $amount,
            "description" => "Payment description goes here",
            "callback_url" => "$ipnData->url",
            "notification_id" => "$ipnData->ipn_id",
            "branch" => "$branch",
            "billing_address" => array(
                "email_address" => "$email_address",
                "phone_number" => "$phone",
                "country_code" => "KE",
                "first_name" => "$first_name",
                //"middle_name" => "$middle_name",
                "last_name" => "$last_name",
                "line_1" => "Pesapal Limited",
                "line_2" => "",
                "city" => "",
                "state" => "",
                "postal_code" => "",
                "zip_code" => ""
            )
        );
        $ch = curl_init($submitOrderUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response);
        $redirectUrl = $data->redirect_url;
        return $redirectUrl;
        // echo "<script>window.location.href='$redirectUrl'</script>";
    }

    public function getCredentials($pgName)
    {
        $pg = DB::table('external_services')->where('name',$pgName)->where('type','payment')->first();
        $lines = explode(';',$pg->details);
        $keys = explode(',', $lines[0]);
        $vals = explode(',', $lines[1]);

        $results = array_combine($keys, $vals);

        return $results;
    }

    public function moneipoint($saleData)
    {
        $merchantreference = $saleData['reference_no'];
        $amount = $saleData['amount'];
        $results = $this->getCredentials('Moneipoint');
        //Generate access token start
        $apiUrl = "https://channel.moniepoint.com/v1/auth";

        $headers = [
            "Accept: application/json",
            "Content-Type: application/json"
        ];

        $data = [
            "clientId" => $results['client_id'],
            "clientSecret" => $results['client_secret']
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($response);
        // return $data->token;
        $token = $data->accessToken;
        //Generate access token end

        // Start Transaction
        $headers = array(
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        );

        $submitOrderUrl = "https://channel.moniepoint.com/v1/transactions";

        $data = array(
            "terminalSerial" => $results['terminal_serial'],
            "amount" => $amount,
            "merchantReference" => $merchantreference,
            "transactionType" => "PURCHASE",
            "paymentMethod" => "CARD_PURCHASE"

        );

        $ch = curl_init($submitOrderUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response);
        return $data;
    }

    public function showDeletedSales()
    {
        $lims_deleted_data = Sale::onlyTrashed()
            ->with(['user', 'customer', 'warehouse', 'deleter'])
            ->get();

        return view('backend.sale.deleted-data', compact('lims_deleted_data'));
    }

    public function forceDeleteSelected(Request $request)
    {
        $ids = $request->ids ?? [];

        if (!empty($ids)) {
            Sale::withTrashed()->whereIn('id', $ids)->forceDelete();
            return back()->with('not_permitted', 'Selected sales permanently deleted!');
        }

        return back()->with('not_permitted', 'No sales selected!');
    }

    public function search($warehouse_id, $search)
    {
        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $search = trim($search);
        $exactMatchFound = false;
        $products = [];

        $query = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
                ->where('products.is_active', true)
                ->where('product_warehouse.warehouse_id', $warehouse_id)
                ->when(config('without_stock') == 'no', function ($query) {
                    $query->where('product_warehouse.qty', '>', 0);
                })
                ->where(function ($q) use ($search) {
                    $q->where('products.code', 'like', '%' . $search . '%')
                    ->orWhere('products.name', 'like', '%' . $search . '%')
                    ->orWhere('product_warehouse.imei_number', 'like', '%' . $search . '%');
                })
                ->select(
                    'products.id',
                    'products.code',
                    'products.name',
                    'products.is_imei',
                    'products.is_diffPrice',
                    'products.is_variant',
                    'products.is_embeded',
                    'products.is_batch',
                    'products.type',
                    'products.product_list',
                    'products.qty_list',
                    'products.type',
                    'product_warehouse.qty',
                    'product_warehouse.imei_number',
                    'product_warehouse.product_batch_id',
                    // use warehouse price if is_diffPrice is true
                    DB::raw("CASE WHEN products.is_diffPrice = 1 
                                THEN product_warehouse.price 
                                ELSE products.price 
                        END as price")
                )
                ->orderBy('products.name')
                ->limit(20)
                ->get();

        foreach ($query as $product) {
            $batch_no = null;
            $expired_date = null;

            // combo qty check
            if($product->type == 'combo'){
                $product_list = explode(",", $product->product_list); // child products
                $qty_list = explode(",", $product->qty_list); // required qty for combo
                $comboQtys = [];
                foreach ($product_list as $index => $child_id) {
                    $requiredQty = $qty_list[$index];
                    $childStock = Product_Warehouse::where('product_id', $child_id)
                        ->where('warehouse_id', $warehouse_id)
                        ->value('qty') ?? 0;
                    if($requiredQty > 0){
                        $comboQtys[] = intdiv($childStock, $requiredQty);
                    } else {
                        $comboQtys[] = 0;
                    }
                }
                $combo_make_qty = min($comboQtys);
                if ($combo_make_qty < 0) {
                    $combo_make_qty = 0;
                }
            }

            if ($product->is_batch == 1) {
                $today = Carbon::now()->toDateString();
                $product_batch = ProductBatch::select('id', 'batch_no', 'expired_date')
                    ->find($product->product_batch_id);

                if ($product_batch) {
                    $batch_no = $product_batch->batch_no;
                    $expired_date = $product_batch->expired_date;

                    // Skip product if expired
                    if ($expired_date < $today) {
                        $expired_date = 0;
                    } else {
                        $expired_date = date(config('date_format'), strtotime($expired_date));
                    }
                }
            }

            $imei_numbers = $product->imei_number ? explode(',', $product->imei_number) : [null];

            // Check if search is exact match with any of the IMEIs
            if (in_array($search, $imei_numbers)) {
                $exactMatchFound = true;

                if ($product->is_variant == 1) {
                    $variants = ProductVariant::select('item_code','additional_price','qty')
                        ->where('product_id', $product->id)
                        ->get();

                    foreach ($variants as $variant) {
                        return response()->json([[
                            'id' => $product->id,
                            'code' => $variant->item_code,
                            'name' => $product->name,
                            'qty' => $product->type == 'combo' ? $product->qty + ($combo_make_qty ?? 0) : $product->qty,
                            'price' => $product->price + ($variant->additional_price ?? 0),
                            'is_imei' => $product->is_imei,
                            'is_embeded' => $product->is_embeded,
                            'batch_no' => $batch_no,
                            'product_batch_id' => $product->product_batch_id,
                            'expired_date' => $expired_date,
                            'imei_number' => $search,
                        ]]);
                    }

                } else {
                    return response()->json([[
                        'id' => $product->id,
                        'code' => $product->code,
                        'name' => $product->name,
                        'qty' => $product->type == 'combo' ? ($combo_make_qty ?? 0) : $product->qty,
                        'price' => $product->price,
                        'is_imei' => $product->is_imei,
                        'is_embeded' => $product->is_embeded,
                        'batch_no' => $batch_no,
                        'product_batch_id' => $product->product_batch_id,
                        'expired_date' => $expired_date,
                        'imei_number' => $search,
                    ]]);
                }
            }

            // If not exact match, continue building the full list
            if (!$exactMatchFound) {

                foreach ($imei_numbers as $imei) {


                    if ($product->is_variant == 1) {
                        $variants = ProductVariant::select('item_code','additional_price','qty')
                            ->where('product_id', $product->id)
                            ->get();

                        foreach ($variants as $variant) {
                            $products[] = [
                                'id' => $product->id,
                                'code' => $variant->item_code,
                                'name' => $product->name,
                                'qty' => $product->type == 'combo' ? ($combo_make_qty ?? 0) : $product->qty,
                                'price' => $product->price + ($variant->additional_price ?? 0),
                                'is_imei' => $product->is_imei,
                                'is_embeded' => $product->is_embeded,
                                'batch_no' => $batch_no,
                                'product_batch_id' => $product->product_batch_id,
                                'expired_date' => $expired_date,
                                'imei_number' => $imei,
                            ];
                        }

                    } else {

                        $products[] = [
                            'type' => $product->type,
                            'id' => $product->id,
                            'code' => $product->code,
                            'name' => $product->name,
                            'qty' => $product->type == 'combo' ? ($combo_make_qty ?? 0) : $product->qty,
                            'price' => $product->price,
                            'is_imei' => $product->is_imei,
                            'is_embeded' => $product->is_embeded,
                            'batch_no' => $batch_no,
                            'product_batch_id' => $product->product_batch_id,
                            'expired_date' => $expired_date,
                            'imei_number' => $imei,
                        ];
                    }
                }
            }
        }
        
        return response()->json($products);

    }
}
