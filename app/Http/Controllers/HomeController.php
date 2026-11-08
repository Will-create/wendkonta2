<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Returns;
use App\Models\ReturnPurchase;
use App\Models\ProductPurchase;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payroll;
use App\Models\Quotation;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Product_Sale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RewardPointSetting;
use App\Models\Product_Warehouse;
use App\Models\Unit;
use Cache;
use DB;
use Auth;
use Printing;
use Rawilk\Printing\Contracts\Printer;
use Spatie\Permission\Models\Role;
use App\Traits\AutoUpdateTrait;
use App\Traits\ENVFilePutContent;
use Illuminate\Support\Facades\Artisan;
use Exception;
use ZipArchive;
use Illuminate\Support\Facades\File;
/*use vendor\autoload;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;*/

class HomeController extends Controller
{
    use AutoUpdateTrait, ENVFilePutContent;

    private $versionUpgradeInfo = [];

	public function __construct()
    {
        if(!config('database.connections.saleprosaas_landlord')) {
            $this->versionUpgradeInfo = $this->isUpdateAvailable();
        }
	}

    public function home()
    {
        return view('backend.home');
    }

    public function index()
    {
        return redirect('dashboard');
    }

    public function documentation()
    {
        $general_setting =  Cache::remember('general_setting', 60*60*24*365, function () {
            return DB::table('general_settings')->latest()->first();
        });
        return view('backend.documentation', compact('general_setting'));
    }

    public function ecomDocumentation()
    {
        $general_setting =  Cache::remember('general_setting', 60*60*24*365, function () {
            return DB::table('general_settings')->latest()->first();
        });
        return view('backend.ecom-documentation', compact('general_setting'));
    }

    public function addonList()
    {
        if(!config('database.connections.saleprosaas_landlord')) {
            $role = Role::find(Auth::user()->role_id);
            if(!$role->hasPermissionTo('addons')) {
                return redirect('dashboard')->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
            }
        }
        return view('backend.addonlist');
    }

    public function dashboard()
    {

        //Write your translatable strings in JSON format below
        // $jsonData = '{
        //     "Available Quantity": "Available Quantity",
        //     "Hide Total Due": "Hide Total Due"
        // }';

        // $translations = json_decode($jsonData, true);

        // $languages = DB::table('languages')->pluck('language');

        // foreach ($languages as $locale) {
        //     foreach ($translations as $key => $value) {
        //         // Optional: Check if this translation already exists to avoid duplication
        //         $exists = DB::table('translations')
        //             ->where('locale', $locale)
        //             ->where('key', $key)
        //             ->exists();

        //         if (!$exists) {
        //             DB::table('translations')->insert([
        //                 'locale' => $locale,
        //                 'group' => 'db',
        //                 'key' => $key,
        //                 'value' => $value,
        //                 'created_at' => now(),
        //                 'updated_at' => now(),
        //             ]);
        //         }
        //     }
        // };

        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();

        if(in_array('restaurant',explode(',',cache()->get('general_setting')->modules))){
            if(Auth::user()->role_id > 2 && isset(Auth::user()->kitchen_id)){

                $result = (new \Modules\Restaurant\Http\Controllers\KitchenController)->dashboard();

                return $result;
            }
        }

        if(Auth::user()->role_id == 5) {
            $customer = Customer::select('id', 'points')->where('user_id', Auth::id())->first();
            $lims_sale_data = Sale::with('warehouse')->where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();
            $lims_payment_data = DB::table('payments')
                           ->join('sales', 'payments.sale_id', '=', 'sales.id')
                           ->where('customer_id', $customer->id)
                           ->select('payments.*', 'sales.reference_no as sale_reference')
                           ->orderBy('payments.created_at', 'desc')
                           ->get();
            $lims_quotation_data = Quotation::with('biller', 'customer', 'supplier', 'user')->orderBy('id', 'desc')->where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();

            $lims_return_data = Returns::with('warehouse', 'customer', 'biller')->where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();
            $lims_reward_point_setting_data = RewardPointSetting::select('per_point_amount')->latest()->first();
            return view('backend.customer_index', compact('customer', 'lims_sale_data', 'lims_payment_data', 'lims_quotation_data', 'lims_return_data', 'lims_reward_point_setting_data'));
        }

        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y")));
        $yearly_sale_amount = [];

        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $product_sale_data = Sale::join('product_sales', 'sales.id','=', 'product_sales.sale_id')
                ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, product_sales.sale_unit_id, sum(product_sales.qty) as sold_qty, sum(product_sales.return_qty) as return_qty, sum(product_sales.total) as sold_amount'))
                ->where('sales.user_id', Auth::id())
                ->whereDate('sales.created_at', '>=' , $start_date)
                ->whereDate('sales.created_at', '<=' , $end_date)
                ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')
                ->get();
            $product_cost = $this->calculateAverageCOGS($product_sale_data);
            $revenue = Sale::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total - shipping_cost'));
            $return = Returns::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $expense = Expense::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $income = Income::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $purchase = Purchase::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $revenue = $revenue - $return + $income;
            $profit = $revenue + $purchase_return - $product_cost - $expense;
        }
        else
        {
            $product_sale_data = Product_Sale::join('sales', 'product_sales.sale_id', '=', 'sales.id')
                ->select(DB::raw('
                    product_sales.product_id,
                    product_sales.product_batch_id,
                    product_sales.sale_unit_id,
                    sum(product_sales.qty) as sold_qty,
                    sum(product_sales.return_qty) as return_qty,
                    sum(product_sales.total) as sold_total
                '))
                ->whereDate('sales.created_at', '>=', $start_date)
                ->whereDate('sales.created_at', '<=', $end_date)
                ->groupBy('product_sales.product_id', 'product_sales.product_batch_id', 'product_sales.sale_unit_id')
                ->get();
            $product_cost = $this->calculateAverageCOGS($product_sale_data);
            $revenue = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total - shipping_cost'));
            $expense = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $income = Income::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $return = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $purchase = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $revenue = $revenue - $return + $income;
            $profit = $revenue + $purchase_return - $product_cost - $expense;
        }

        //cash flow of last 6 months
        $start = strtotime(date('Y-m-01', strtotime('-6 month', strtotime(date('Y-m-d') ))));
        $end = strtotime(date('Y-m-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y")))));

        while($start < $end)
        {
            $start_date = date("Y-m", $start).'-'.'01';
            $end_date = date("Y-m", $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));

            if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own') {
                $recieved_amount = DB::table('payments')->whereNotNull('sale_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
                $sent_amount = DB::table('payments')->whereNotNull('purchase_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
                $return_amount = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
                $purchase_return_amount = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
                $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
                $payroll_amount = Payroll::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
            }
            else {
                $recieved_amount = DB::table('payments')->whereNotNull('sale_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
                $sent_amount = DB::table('payments')->whereNotNull('purchase_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
                $return_amount = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
                $purchase_return_amount = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
                $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
                $payroll_amount = Payroll::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            }
            $sent_amount = $sent_amount + $return_amount + $expense_amount + $payroll_amount;

            $payment_recieved[] = number_format((float)($recieved_amount + $purchase_return_amount), config('decimal'), '.', '');
            $payment_sent[] = number_format((float)$sent_amount, config('decimal'), '.', '');
            $month[] = __('db.month_'.date('n', strtotime($start_date)));
            $start = strtotime("+1 month", $start);
        }
        // yearly report
        $start = strtotime(date("Y") .'-01-01');
        $end = strtotime(date("Y") .'-12-31');
        while($start < $end)
        {
            $start_date = date("Y").'-'.date('m', $start).'-'.'01';
            $end_date = date("Y").'-'.date('m', $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));
            if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own') {
                $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
                $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('grand_total');
            }
            else{
                $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
                $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            }
            $yearly_sale_amount[] = number_format((float)$sale_amount, config('decimal'), '.', '');
            $yearly_purchase_amount[] = number_format((float)$purchase_amount, config('decimal'), '.', '');
            $start = strtotime("+1 month", $start);
        }
        //making strict mode true for this query
        config()->set('database.connections.mysql.strict', true);
        DB::reconnect();
        //fetching data for auto updates
        if(!config('database.connections.saleprosaas_landlord') && Auth::user()->role_id <= 2) {
            $versionUpgradeData = [];
            $versionUpgradeData = $this->versionUpgradeInfo;
        }
        else {
            $versionUpgradeData = [];
        }
        return view('backend.index', compact('revenue', 'purchase', 'expense', 'return', 'purchase_return', 'profit', 'payment_recieved', 'payment_sent', 'month', 'yearly_sale_amount', 'yearly_purchase_amount', 'versionUpgradeData'));
    }

    public function newVersionReleasePage()
    {
		// Below line is deprecated, this code is needed for the client version 1.5.1 and below
        $this->dataWriteInENVFile('APP_ENV', 'local');
		// Below line is deprecated, this code is needed for the client version 1.5.1 and below

        $versionUpgradeData = [];
        $versionUpgradeData = $this->versionUpgradeInfo;
        return view('version_upgrade.index', compact('versionUpgradeData'));
    }

    public function versionUpgrade(Request $request) {
        $versionUpgradeData = [];
        $versionUpgradeData = $this->versionUpgradeInfo;
        $version_upgrade_file_url = $this->versionUpgradeFileUrl($request->purchasecode);

        if (!$version_upgrade_file_url) {
            return redirect()->back()->with('not_permitted', 'Wrong Purchase Code !');
        }

        try {
            //Check file is exist
            $header_array = @get_headers($version_upgrade_file_url);
            if(!strpos($header_array[0], '200')) {
                throw new Exception("Something wrong. Please contact with support team.");
            }

            $this->fileTransferProcess($version_upgrade_file_url);

            if ($versionUpgradeData['latest_version_db_migrate_enable']==true){
                Artisan::call('migrate');
                Artisan::call('db:seed');
            }

            Artisan::call('optimize:clear');

            $this->dataWriteInENVFile('VERSION', $versionUpgradeData['demo_version']);

            return redirect()->back()->with('message', 'Version Upgraded Successfully !!!');
        }
        catch(Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function fileTransferProcess($version_upgrade_file_url)
    {
        $remote_file_name = pathinfo($version_upgrade_file_url)['basename'];
        $local_file = base_path('/'.$remote_file_name);
        $copy = copy($version_upgrade_file_url, $local_file);
        if ($copy) {
            // ****** Unzip ********
            $zip = new ZipArchive;
            $file = base_path($remote_file_name);
            $res = $zip->open($file);
            if ($res === TRUE) {
                $zip->extractTo(base_path('/'));
                $zip->close();

                // ****** Delete Zip File ******
                File::delete(base_path($remote_file_name));
            }
        }
    }

    public function yearlyBestSellingPrice()
    {
        //making strict mode false for this query
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();
        $yearly_best_selling_price = Product_Sale::join('products', 'products.id', '=', 'product_sales.product_id')
        ->select(DB::raw('products.name as product_name, products.code as product_code, products.image as product_images, sum(total) as total_price'))
        ->whereDate('product_sales.created_at', '>=' , date("Y").'-01-01')
        ->whereDate('product_sales.created_at', '<=' , date("Y").'-12-31')
        ->groupBy('products.code')
        ->orderBy('total_price', 'desc')
        ->take(5)
        ->get();

        return response()->json($yearly_best_selling_price);
    }

    public function yearlyBestSellingQty()
    {
        //making strict mode false for this query
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();
        $yearly_best_selling_qty = Product_Sale::join('products', 'products.id', '=', 'product_sales.product_id')
        ->select(DB::raw('products.name as product_name, products.code as product_code, products.image as product_images, sum(product_sales.qty) as sold_qty'))
        ->whereDate('product_sales.created_at', '>=' , date("Y").'-01-01')
        ->whereDate('product_sales.created_at', '<=' , date("Y").'-12-31')
        ->groupBy('products.code')
        ->orderBy('sold_qty', 'desc')
        ->take(5)
        ->get();

        return response()->json($yearly_best_selling_qty);
    }

    public function monthlyBestSellingQty()
    {
        //making strict mode false for this query
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();
        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y")));
        $best_selling_qty = Product_Sale::join('products', 'products.id', '=', 'product_sales.product_id')
        ->select(DB::raw('products.name as product_name, products.code as product_code, products.image as product_images, sum(product_sales.qty) as sold_qty'))
        ->whereDate('product_sales.created_at', '>=' , $start_date)
        ->whereDate('product_sales.created_at', '<=' , $end_date)
        ->groupBy('products.code')
        ->orderBy('sold_qty', 'desc')
        ->take(5)
        ->get();

        return response()->json($best_selling_qty);
    }

    public function recentSale()
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $recent_sale = Sale::join('customers', 'customers.id', '=', 'sales.customer_id')->select('sales.id','sales.reference_no','sales.sale_status','sales.created_at','sales.grand_total','sales.user_id','customers.name')->orderBy('id', 'desc')->where('sales.user_id', Auth::id())->take(5)->get();
            return response()->json($recent_sale);
        }
        else
        {
            $recent_sale = Sale::join('customers', 'customers.id', '=', 'sales.customer_id')->select('sales.id','sales.reference_no','sales.sale_status','sales.created_at','sales.grand_total','customers.name')->orderBy('id', 'desc')->take(5)->get();
            return response()->json($recent_sale);
        }
    }

    public function recentPurchase()
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $recent_purchase = Purchase::leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')->select('purchases.id','purchases.reference_no','purchases.status','purchases.created_at','purchases.grand_total','purchases.user_id','suppliers.name')->orderBy('id', 'desc')->where('purchases.user_id', Auth::id())->take(5)->get();
            return response()->json($recent_purchase);
        }
        else
        {
            $recent_purchase = Purchase::leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')->select('purchases.id','purchases.reference_no','purchases.status','purchases.created_at','purchases.grand_total','suppliers.name')->orderBy('id', 'desc')->take(5)->get();
            return response()->json($recent_purchase);
        }
    }

    public function recentQuotation()
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $recent_quotation = Quotation::join('customers', 'customers.id', '=', 'quotations.customer_id')->select('quotations.id','quotations.reference_no','quotations.quotation_status','quotations.created_at','quotations.grand_total','quotations.user_id','customers.name')->orderBy('id', 'desc')->where('quotations.user_id', Auth::id())->take(5)->get();
            return response()->json($recent_quotation);
        }
        else
        {
            $recent_quotation = Quotation::join('customers', 'customers.id', '=', 'quotations.customer_id')->select('quotations.id','quotations.reference_no','quotations.quotation_status','quotations.created_at','quotations.grand_total','customers.name')->orderBy('id', 'desc')->take(5)->get();
            return response()->json($recent_quotation);
        }
    }

    public function recentPayment()
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $recent_payment = Payment::select('id','payment_reference','amount','paying_method','created_at','user_id')->orderBy('id', 'desc')->where('user_id', Auth::id())->take(5)->get();
            return response()->json($recent_payment);
        }
        else
        {
            $recent_payment = Payment::select('id','payment_reference','amount','paying_method','created_at')->orderBy('id', 'desc')->take(5)->get();
            return response()->json($recent_payment);
        }
    }

    public function dashboardFilter($start_date, $end_date, $warehouse_id)
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own') {
            config()->set('database.connections.mysql.strict', false);
            DB::reconnect();
            $product_sale_data = Sale::join('product_sales', 'sales.id','=', 'product_sales.sale_id')
                ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, sale_unit_id, sum(product_sales.qty) as sold_qty, sum(product_sales.total) as sold_amount'))
                ->where('sales.user_id', Auth::id())
                ->whereDate('sales.created_at', '>=' , $start_date)
                ->whereDate('sales.created_at', '<=' , $end_date)
                ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')
                ->get();
            config()->set('database.connections.mysql.strict', true);
            DB::reconnect();
            $product_cost = $this->calculateAverageCOGS($product_sale_data);
            $revenue = Sale::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total - shipping_cost'));
            $return = Returns::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $expense = Expense::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $income = Income::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $purchase = Purchase::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            $revenue = $revenue - $return + $income;
            $profit = $revenue + $purchase_return - $product_cost - $expense;
        }
        else {
            config()->set('database.connections.mysql.strict', false);
            DB::reconnect();
            $q = Sale::join('product_sales', 'sales.id','=', 'product_sales.sale_id')
                ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, product_sales.sale_unit_id, sum(product_sales.qty) as sold_qty, sum(product_sales.return_qty) as return_qty, sum(product_sales.total) as sold_amount'))
                //->where('sales.user_id', Auth::id())
                ->whereDate('sales.created_at', '>=' , $start_date)
                ->whereDate('sales.created_at', '<=' , $end_date);
                //return $start_date.$end_date.$warehouse_id;
            if($warehouse_id == 0)
            {

                $product_sale_data = $q->groupBy('product_sales.product_id', 'product_sales.product_batch_id')->get();
            }
            else
            {
                $product_sale_data = $q->where('sales.warehouse_id',$warehouse_id)
                                        ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')
                                        ->get();
            }


            config()->set('database.connections.mysql.strict', true);
            DB::reconnect();
            $product_cost = $this->calculateAverageCOGS($product_sale_data);

            if($warehouse_id == 0) {
                $revenue = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total - shipping_cost'));
                $return = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
                $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('grand_total');
            }

            else {

                $revenue = Sale::where('warehouse_id',$warehouse_id)
                ->whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)
                ->sum(DB::raw('grand_total - shipping_cost'));

                $return = Returns::where('warehouse_id',$warehouse_id)
                        ->whereDate('created_at', '>=' , $start_date)
                        ->whereDate('created_at', '<=' , $end_date)
                        ->sum('grand_total');

                $purchase_return = ReturnPurchase::where('warehouse_id',$warehouse_id)
                                ->whereDate('created_at', '>=' , $start_date)
                                ->whereDate('created_at', '<=' , $end_date)
                                ->sum('grand_total');
            }

            $expense = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $income = Income::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            $revenue = $revenue - $return + $income;
            $profit = $revenue + $purchase_return - $product_cost - $expense;

            $data[0] = $revenue;
            $data[1] = $return;
            $data[2] = $profit;
            $data[3] = $purchase_return;
        }
        return $data;
    }

    public function calculateAverageCOGS($product_sale_data)
    {
        // Initialize total product cost
        $product_cost = 0;

        // Loop through each sold product entry
        foreach ($product_sale_data as $key => $product_sale) {

            // Fetch product details for the sold product
            $product_data = Product::select('type', 'product_list', 'variant_list', 'qty_list')
                ->find($product_sale->product_id);

            // If product is a combo (bundle of multiple products)
            if($product_data && $product_data->type == 'combo') {
                $product_list = explode(",", $product_data->product_list);

                // Handle variants if present
                if($product_data->variant_list)
                    $variant_list = explode(",", $product_data->variant_list);
                else
                    $variant_list = [];

                // Quantities of each product in the combo
                $qty_list = explode(",", $product_data->qty_list);

                // Loop through each product inside the combo
                foreach ($product_list as $index => $product_id) {

                    // If product has variants, fetch purchase data accordingly
                    if(count($variant_list) && $variant_list[$index]) {
                        $product_purchase_data = ProductPurchase::where([
                            ['product_id', $product_id],
                            ['variant_id', $variant_list[$index] ]
                        ])
                        ->select('recieved', 'purchase_unit_id', 'total')
                        ->get();
                    }
                    else {
                        // Fetch all purchases for this product
                        $product_purchase_data = ProductPurchase::where('product_id', $product_id)
                            ->select('recieved', 'purchase_unit_id', 'total')
                            ->get();
                    }

                    $total_received_qty = 0;
                    $total_purchased_amount = 0;

                    // Calculate sold quantity of this sub-product in the combo
                    $sold_qty = ($product_sale->sold_qty - $product_sale->return_qty) * $qty_list[$index];

                    // Fetch all unit conversion data
                    $units = Unit::select('id', 'operator', 'operation_value')->get();

                    // Loop through all purchases for this product
                    foreach ($product_purchase_data as $key => $product_purchase) {
                        $purchase_unit_data = $units->where('id',$product_purchase->purchase_unit_id)->first();

                        // Convert received quantity into base unit
                        if($purchase_unit_data->operator == '*')
                            $total_received_qty += $product_purchase->recieved * $purchase_unit_data->operation_value;
                        else
                            $total_received_qty += $product_purchase->recieved / $purchase_unit_data->operation_value;

                        // Accumulate purchase cost
                        $total_purchased_amount += $product_purchase->total;
                    }

                    // Compute average cost (purchase amount / total received qty)
                    if($total_received_qty)
                        $averageCost = $total_purchased_amount / $total_received_qty;
                    else
                        $averageCost = 0;

                    // Add to total product cost
                    $product_cost += $sold_qty * $averageCost;
                }
            }
            else {
                // For normal products (not combo)

                // Fetch purchase data depending on batch or variant
                if($product_sale->product_batch_id) {
                    $product_purchase_data = ProductPurchase::where([
                        ['product_id', $product_sale->product_id],
                        ['product_batch_id', $product_sale->product_batch_id]
                    ])
                    ->select('recieved', 'purchase_unit_id', 'total')
                    ->get();
                }
                elseif($product_sale->variant_id) {
                    $product_purchase_data = ProductPurchase::where([
                        ['product_id', $product_sale->product_id],
                        ['variant_id', $product_sale->variant_id]
                    ])
                    ->select('recieved', 'purchase_unit_id', 'total')
                    ->get();
                }
                else {
                    $product_purchase_data = ProductPurchase::where('product_id', $product_sale->product_id)
                        ->select('recieved', 'purchase_unit_id', 'total')
                        ->get();
                }

                $total_received_qty = 0;
                $total_purchased_amount = 0;

                // Fetch all unit conversion data
                $units = Unit::select('id', 'operator', 'operation_value')->get();

                // Convert sold quantity into base unit if sale unit is defined
                if($product_sale->sale_unit_id) {
                    $sale_unit_data = $units->where('id', $product_sale->sale_unit_id)->first();
                    if($sale_unit_data->operator == '*')
                        $sold_qty = ($product_sale->sold_qty - $product_sale->return_qty) * $sale_unit_data->operation_value;
                    else
                        $sold_qty = ($product_sale->sold_qty - $product_sale->return_qty) / $sale_unit_data->operation_value;
                }
                else {
                    // If no unit conversion, just take raw sold qty
                    $sold_qty = ($product_sale->sold_qty - $product_sale->return_qty);
                }

                // Loop through purchases to accumulate received qty and purchase amount
                foreach ($product_purchase_data as $key => $product_purchase) {
                    $purchase_unit_data = $units->where('id', $product_purchase->purchase_unit_id)->first();
                    if($purchase_unit_data) {
                        if($purchase_unit_data->operator == '*')
                            $total_received_qty += $product_purchase->recieved * $purchase_unit_data->operation_value;
                        else
                            $total_received_qty += $product_purchase->recieved / $purchase_unit_data->operation_value;

                        $total_purchased_amount += $product_purchase->total;
                    }
                }

                // Calculate average cost for the product
                if($total_received_qty)
                    $averageCost = $total_purchased_amount / $total_received_qty;
                else
                    $averageCost = 0;

                // Add to total product cost
                $product_cost += $sold_qty * $averageCost;
            }
        }

        // Return the total calculated product cost (COGS)
        return $product_cost;
    }


    public function myTransaction($year, $month)
    {
        $start = 1;
        $number_of_day = date('t', mktime(0, 0, 0, $month, 1, $year));
        while($start <= $number_of_day)
        {
            if($start < 10)
                $date = $year.'-'.$month.'-0'.$start;
            else
                $date = $year.'-'.$month.'-'.$start;
            $sale_generated[$start] = Sale::whereDate('created_at', $date)->where('user_id', Auth::id())->count();
            $sale_grand_total[$start] = Sale::whereDate('created_at', $date)->where('user_id', Auth::id())->sum('grand_total');
            $purchase_generated[$start] = Purchase::whereDate('created_at', $date)->where('user_id', Auth::id())->count();
            $purchase_grand_total[$start] = Purchase::whereDate('created_at', $date)->where('user_id', Auth::id())->sum('grand_total');
            $quotation_generated[$start] = Quotation::whereDate('created_at', $date)->where('user_id', Auth::id())->count();
            $quotation_grand_total[$start] = Quotation::whereDate('created_at', $date)->where('user_id', Auth::id())->sum('grand_total');
            $start++;
        }
        $start_day = date('w', strtotime($year.'-'.$month.'-01')) + 1;
        $prev_year = date('Y', strtotime('-1 month', strtotime($year.'-'.$month.'-01')));
        $prev_month = date('m', strtotime('-1 month', strtotime($year.'-'.$month.'-01')));
        $next_year = date('Y', strtotime('+1 month', strtotime($year.'-'.$month.'-01')));
        $next_month = date('m', strtotime('+1 month', strtotime($year.'-'.$month.'-01')));
        return view('backend.user.my_transaction', compact('start_day', 'year', 'month', 'number_of_day', 'prev_year', 'prev_month', 'next_year', 'next_month', 'sale_generated', 'sale_grand_total','purchase_generated', 'purchase_grand_total','quotation_generated', 'quotation_grand_total'));
    }

    public function switchTheme($theme)
    {
        setcookie('theme', $theme, time() + (86400 * 365), "/");
    }

    public function sessionRenew(Request $request)
    {
        return response()->json('success');
    }
}
