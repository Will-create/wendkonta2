<?php

use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

use App\Http\Controllers\DemoAutoUpdateController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerGroupController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\BillerController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\DiscountPlanController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\IncomeCategoryController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\MoneyTransferController;
use App\Http\Controllers\Api\ReturnSaleController;
use App\Http\Controllers\Api\ReturnPurchaseController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SmsTemplateController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
$middleware = ['api'];
if(config('database.connections.saleprosaas_landlord')) {
    $middleware[] = InitializeTenancyByDomain::class;
    $middleware[] = PreventAccessFromCentralDomains::class;
}

Route::controller(DemoAutoUpdateController::class)->group(function () {
    Route::get('fetch-data-general', 'fetchDataGeneral')->name('fetch-data-general');
    Route::get('fetch-data-upgrade', 'fetchDataForAutoUpgrade')->name('data-read');
    Route::get('fetch-data-bugs', 'fetchDataForBugs')->name('fetch-data-bugs');
});


Route::group(['middleware' => $middleware], function () {

    Route::get('/get-registration-form-data', [RegisterController::class, 'getRegistrationFormData']);

    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/check', [LoginController::class, 'checkLicense']);

    Route::controller(HomeController::class)->middleware('auth:sanctum')->group(function () {
        Route::get('/get-user', 'getUser');
        Route::get('/dashboard', 'dashboard');
        Route::get('/yearly-best-selling-price', 'yearlyBestSellingPrice');
        Route::get('/yearly-best-selling-qty', 'yearlyBestSellingQty');
        Route::get('/monthly-best-selling-qty', 'monthlyBestSellingQty');
        Route::get('/recent-sale', 'recentSale');
        Route::get('/recent-purchase', 'recentPurchase');
        Route::get('/recent-quotation', 'recentQuotation');
        Route::get('/recent-payment', 'recentPayment');
        Route::get('switch-theme/{theme}', 'switchTheme')->name('switchTheme');
        Route::get('/dashboard-filter/{start_date}/{end_date}/{warehouse_id}', 'dashboardFilter');
        Route::get('addon-list', 'addonList');
        Route::get('my-transactions/{year}/{month}', 'myTransaction');
    });

    Route::group(['middleware'=>['auth:sanctum','common','validate_mobile_token']],function () {
        Route::get('test',[BrandController::class,'test']);
        Route::apiResource('brands', BrandController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('units', UnitController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('currencies', CurrencyController::class);
        Route::get('get-all-units',[UnitController::class,'getAllUnit']);
        Route::apiResource('warehouses', WarehouseController::class);
        Route::apiResource('taxes', TaxController::class);
        Route::apiResource('purchases', PurchaseController::class);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('billers', BillerController::class);
        Route::apiResource('customergroups', CustomerGroupController::class);
        Route::apiResource('sales', SaleController::class);
        Route::get('generate-code',[ProductController::class,'generateCode']);
        Route::post('pos-setting', [SettingController::class,'posSettingStore'])->name('setting.posStore');
        Route::post('general-setting', [SettingController::class,'generalSettingStore'])->name('setting.generalStore');
        Route::apiResource('discount-plans', DiscountPlanController::class);
        Route::apiResource('discounts', DiscountController::class);
        Route::get('discounts/product-search/{code}', [DiscountController::class,'productSearch']);
        Route::apiResource('expensecategories', ExpenseCategoryController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('incomecategories', IncomeCategoryController::class);
        Route::apiResource('incomes', IncomeController::class);

        Route::controller(ExpenseCategoryController::class)->group(function () {
            Route::get('expensecategories/gencode', 'generateCode');
            Route::post('expensecategories/import', 'import')->name('expense_category.import');
            Route::post('expensecategories/deletebyselection', 'deleteBySelection');
            Route::get('expensecategories/all', 'expenseCategoriesAll')->name('expense_category.all');;
        });

        Route::controller(ExpenseController::class)->group(function () {
            Route::post('expenses/expense-data', 'expenseData')->name('expenses.data');
            Route::post('expenses/deletebyselection', 'deleteBySelection');
        });

        // IncomeCategory & Income Start
        Route::controller(IncomeCategoryController::class)->group(function () {
            Route::get('incomecategories/gencode', 'generateCode');
            Route::post('incomecategories/import', 'import')->name('income_category.import');
            Route::post('incomecategories/deletebyselection', 'deleteBySelection');
            Route::get('incomecategories/all', 'incomeCategoriesAll')->name('income_category.all');;
        });

        Route::controller(IncomeController::class)->group(function () {
            Route::post('incomes/income-data', 'incomeData')->name('incomes.data');
            Route::post('incomes/deletebyselection', 'deleteBySelection');
        });

        // IncomeCategory & Income End

        // Settings Start
        Route::controller(SettingController::class)->group(function () {
            Route::prefix('setting')->group(function () {
                Route::post('hrm-setting', 'hrmSettingStore')->name('setting.hrmStore');

            });
            Route::post('mail-settings', 'mailSettingStore')->name('setting.mailStore');
            Route::post('payment-gateways','gatewayUpdate')->name('setting.gateway.update');
            Route::get('backup', 'backup')->name('setting.backup');
        });

        // Notifications
        Route::controller(NotificationController::class)->group(function () {
            Route::prefix('notifications')->group(function () {
                Route::get('/', 'index')->name('notifications.index');
                Route::post('store', 'store')->name('notifications.store');
                Route::get('mark-as-read', 'markAsRead');
            });
        });

        Route::controller(AccountController::class)->group(function () {
            Route::get('make-default/{id}', 'makeDefault');
            Route::get('balancesheet', 'balanceSheet')->name('accounts.balancesheet');
            Route::post('account-statement', 'accountStatement')->name('accounts.statement');
            Route::get('accounts/all', 'accountsAll')->name('account.all');
        });
        Route::apiResource('accounts', AccountController::class);

        Route::apiResource('money-transfers', MoneyTransferController::class);

        // Return Sale & Purchase
        Route::controller(ReturnSaleController::class)->group(function () {
            Route::prefix('return-sale')->group(function () {
                Route::post('return-data', 'returnData');
                Route::get('getcustomergroup/{id}', 'getCustomerGroup')->name('return-sale.getcustomergroup');
                Route::post('sendmail', 'sendMail')->name('return-sale.sendmail');
                Route::get('getproduct/{id}', 'getProduct')->name('return-sale.getproduct');
                Route::get('lims_product_search', 'limsProductSearch')->name('product_return-sale.search');
                Route::get('product_return/{id}', 'productReturnData');
                Route::post('deletebyselection', 'deleteBySelection');
            });
        });
        Route::apiResource('return-sale', ReturnSaleController::class);


        Route::controller(ReturnPurchaseController::class)->group(function () {
            Route::prefix('return-purchase')->group(function () {
                Route::post('return-data', 'returnData');
                Route::get('getcustomergroup/{id}', 'getCustomerGroup')->name('return-purchase.getcustomergroup');
                Route::post('sendmail', 'sendMail')->name('return-purchase.sendmail');
                Route::get('getproduct/{id}', 'getProduct')->name('return-purchase.getproduct');
                Route::get('lims_product_search', 'limsProductSearch')->name('product_return-purchase.search');
                Route::get('product_return/{id}', 'productReturnData');
                Route::post('deletebyselection', 'deleteBySelection');
            });
        });
        Route::apiResource('return-purchase', ReturnPurchaseController::class);
        Route::apiResource('transfers', TransferController::class);

        Route::controller(QuotationController::class)->group(function () {
            Route::prefix('quotations')->group(function () {
                Route::post('quotation-data', 'quotationData')->name('quotations.data');
                Route::get('product_quotation/{id}','productQuotationData');
                Route::get('lims_product_search', 'limsProductSearch')->name('product_quotation.search');
                Route::get('getcustomergroup/{id}', 'getCustomerGroup')->name('quotation.getcustomergroup');
                Route::get('getproduct/{id}', 'getProduct')->name('quotation.getproduct');
                Route::get('{id}/create_sale', 'createSale')->name('quotation.create_sale');
                Route::get('{id}/create_purchase', 'createPurchase')->name('quotation.create_purchase');
                Route::post('sendmail', 'sendMail')->name('quotation.sendmail');
                Route::post('deletebyselection', 'deleteBySelection');
            });
        });

        Route::resource('quotations', QuotationController::class);

        //Sms Template
        Route::resource('smstemplates',SmsTemplateController::class);

        Route::controller(ReportController::class)->group(function () {
            Route::prefix('report')->group(function () {
                Route::get('product-quantity-alert', 'productQuantityAlert')->name('report.qtyAlert');
                Route::get('daily-sale-objective', 'dailySaleObjective')->name('report.dailySaleObjective');
                Route::post('daily-sale-objective-data', 'dailySaleObjectiveData');
                Route::get('product-expiry', 'productExpiry')->name('report.productExpiry');
                Route::get('warehouse_stock', 'warehouseStock')->name('report.warehouseStock');
                Route::post('daily-sale/{year}/{month}', 'dailySale');
                Route::post('daily_sale/{year}/{month}', 'dailySaleByWarehouse')->name('report.dailySaleByWarehouse');
                Route::post('monthly-sale/{year}', 'monthlySale');
                Route::post('monthly_sale/{year}', 'monthlySaleByWarehouse')->name('report.monthlySaleByWarehouse');
                Route::post('daily-purchase/{year}/{month}', 'dailyPurchase');
                Route::post('daily_purchase/{year}/{month}', 'dailyPurchaseByWarehouse')->name('report.dailyPurchaseByWarehouse');
                Route::post('monthly-purchase/{year}', 'monthlyPurchase');
                Route::post('monthly_purchase/{year}', 'monthlyPurchaseByWarehouse')->name('report.monthlyPurchaseByWarehouse');
                Route::get('best-seller', 'bestSeller');
                Route::post('best-seller', 'bestSellerByWarehouse')->name('report.bestSellerByWarehouse');
                Route::post('profit-loss', 'profitLoss')->name('report.profitLoss');
                Route::post('product-report', 'productReportData')->name('report.product');
                Route::post('product_report_data', 'productReportData');
                Route::post('purchase', 'purchaseReportData')->name('report.purchase');
                Route::post('purchase_report_data', 'purchaseReportData');
                Route::post('sale-report', 'saleReportData')->name('report.sale');
                Route::post('sale_report_data', 'saleReportData');
                Route::post('challan-report', 'challanReport')->name('report.challan');
                Route::post('sale-report-chart', 'saleReportChart')->name('report.saleChart');
                Route::post('payment-report-by-date', 'paymentReportByDate')->name('report.paymentByDate');
                Route::post('warehouse_report', 'warehouseReport')->name('report.warehouse');
                Route::post('warehouse-sale-data', 'warehouseSaleData');
                Route::post('warehouse-purchase-data', 'warehousePurchaseData');
                Route::post('warehouse-expense-data', 'warehouseExpenseData');
                Route::post('warehouse-quotation-data', 'warehouseQuotationData');
                Route::post('warehouse-return-data', 'warehouseReturnData');
                Route::post('user-report', 'userSalePurchaseQuotationTransferPaymentExpensePayrollData')->name('report.user');
                Route::post('user-sale-data', 'userSaleData');
                Route::post('user-purchase-data', 'userPurchaseData');
                Route::post('user-expense-data', 'userExpenseData');
                Route::post('user-quotation-data', 'userQuotationData');
                Route::post('user-payment-data', 'userPaymentData');
                Route::post('user-transfer-data', 'userTransferData');
                Route::post('user-payroll-data', 'userPayrollData');
                Route::post('biller-report', 'billerSaleQuotationPaymentData')->name('report.biller');
                Route::post('biller-sale-data','billerSaleData');
                Route::post('biller-quotation-data','billerQuotationData');
                Route::post('biller-payment-data','billerPaymentData');
                Route::post('customer-report', 'CustomerSalePaymentQuotationReturnData')->name('report.customer');
                Route::post('customer-sale-data', 'customerSaleData');
                Route::post('customer-payment-data', 'customerPaymentData');
                Route::post('customer-quotation-data', 'customerQuotationData');
                Route::post('customer-return-data', 'customerReturnData');
                Route::post('customer-group', 'CustomerGroupSalePaymentQuotationReturnData')->name('report.customer_group');
                Route::post('customer-group-sale-data', 'customerGroupSaleData');
                Route::post('customer-group-payment-data', 'customerGroupPaymentData');
                Route::post('customer-group-quotation-data', 'customerGroupQuotationData');
                Route::post('customer-group-return-data', 'customerGroupReturnData');
                Route::post('supplier', 'supplierPurchasePaymentReturnQuotationData')->name('report.supplier');
                Route::post('supplier-purchase-data', 'supplierPurchaseData');
                Route::post('supplier-payment-data', 'supplierPaymentData');
                Route::post('supplier-return-data', 'supplierReturnData');
                Route::post('supplier-quotation-data', 'supplierQuotationData');
                Route::post('customer-due-report', 'customerDueReportByDate')->name('report.customerDueByDate');
                Route::post('customer-due-report-data', 'customerDueReportData');
                Route::post('supplier-due-report', 'supplierDueReportByDate')->name('report.supplierDueByDate');
                Route::post('supplier-due-report-data', 'supplierDueReportByDate');
            });
        });

        Route::controller(UserController::class)->group(function () {
            Route::get('user/profile/{id}', 'profile')->name('user.profile');
            Route::put('user/update-profile/{id}', 'profileUpdate')->name('user.profileUpdate');
            Route::put('user/changepass/{id}', 'changePassword')->name('user.password');
            Route::get('user/genpass', 'generatePassword');
            Route::post('user/deletebyselection', 'deleteBySelection');
            Route::get('user/notification', 'notificationUsers')->name('user.notification');
            Route::get('user/all', 'allUsers')->name('user.all');
        });
        Route::resource('users', UserController::class);
    });

});
