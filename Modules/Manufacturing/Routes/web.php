<?php

use App\Http\Controllers\ProductController;
use Modules\Manufacturing\Http\Controllers\ProductionController;
use Modules\Manufacturing\Http\Controllers\RecipeController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

if(config('database.connections.saleprosaas_landlord')) {
    Route::middleware(['common', 'auth', 'active', InitializeTenancyByDomain::class,PreventAccessFromCentralDomains::class])->group(function () {
        //production routes
        Route::controller(ProductionController::class)->group(function () {
            Route::prefix('manufacturing/productions')->group(function () {
                Route::post('production-data', 'productionData')->name('productions.data');
                Route::get('product_production/{id}', 'productProductionData');
            });
        });
        Route::resource('manufacturing/productions',ProductionController::class)->except([ 'show']);
        Route::resource('manufacturing/recipes',RecipeController::class)->except([ 'show']);
        Route::post('manufacturing/products/product-data',[ProductController::class,'productData'])->name ('get-products');
        Route::post('manufacturing/product-data',[RecipeController::class,'productData'])->name ('manufacturing.product-data');
        Route::get('manufacturing/recipes/lims_product_search',[ProductController::class,'limsProductSearch'])->name ('product.search');
        Route::post('manufacturing/get-Ingredients',[ProductionController::class,'getIngredients'])->name ('get-Ingredients');
        Route::post('products/getdata/{id}/{variant_id}',[ProductController::class,'getData'])->name('products.getdata');
        Route::prefix('manufacturing')->group(function() {
            Route::get('/', 'ManufacturingController@index');
        });
    });
}
else {
    Route::middleware(['common', 'auth', 'active'])->group(function () {
        //production routes
        Route::controller(ProductionController::class)->group(function () {
            Route::prefix('manufacturing/productions')->group(function () {
                Route::post('production-data', 'productionData')->name('productions.data');
                Route::get('product_production/{id}', 'productProductionData');
            });
        });
        Route::resource('manufacturing/productions',ProductionController::class)->except([ 'show']);
        Route::resource('manufacturing/recipes',RecipeController::class)->except([ 'show']);
        Route::post('manufacturing/products/product-data',[ProductController::class,'productData'])->name ('get-products');
        Route::post('manufacturing/product-data',[RecipeController::class,'productData'])->name ('manufacturing.product-data');
        Route::get('manufacturing/recipes/lims_product_search',[ProductController::class,'limsProductSearch'])->name ('product.search');
        Route::post('manufacturing/get-Ingredients',[ProductionController::class,'getIngredients'])->name ('get-Ingredients');
        Route::post('products/getdata/{id}/{variant_id}',[ProductController::class,'getData'])->name('products.getdata');
        Route::prefix('manufacturing')->group(function() {
            Route::get('/', 'ManufacturingController@index');
        });
    });
}







