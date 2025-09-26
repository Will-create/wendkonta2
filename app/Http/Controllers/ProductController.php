<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use File;
use DNS1D;
use Exception;
use Keygen\Keygen;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Brand;
use App\Models\Barcode;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Traits\TenantInfo;
use App\Models\CustomField;
use App\Models\GeneralSetting;
use App\Traits\CacheForget;
use Illuminate\Support\Str;
use App\Models\ProductBatch;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Models\ProductPurchase;
use Illuminate\Validation\Rule;
use App\Models\Product_Supplier;
use App\Models\Product_Warehouse;
use App\Models\Scopes\ExcludeRecipe;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManager;
use Spatie\Permission\Models\Permission;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class ProductController extends Controller
{
    use CacheForget;
    use TenantInfo;

    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('products-index')){
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_brand_list = Brand::where('is_active', true)->get();
            $lims_category_list = Category::where('is_active', true)->get();
            $lims_unit_list = Unit::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();

            $warehouse_id = 0;
            $product_type = 'all';
            $brand_id = 0;
            $category_id = 0;
            $unit_id = 0;
            $tax_id = 0;
            $imeiorvariant = 0;

            if ($request->input('warehouse_id')) {
                $warehouse_id = $request->input('warehouse_id');
            } else if (Auth::user()->warehouse_id) {
                $warehouse_id = Auth::user()->warehouse_id;
            }
            if ($request->input('product_type')) $product_type = $request->input('product_type');
            if ($request->input('brand_id')) $brand_id = $request->input('brand_id');
            if ($request->input('category_id')) $category_id = $request->input('category_id');
            if ($request->input('unit_id')) $unit_id = $request->input('unit_id');
            if ($request->input('tax_id')) $tax_id = $request->input('tax_id');
            if ($request->input('imeiorvariant')) $imeiorvariant = $request->input('imeiorvariant');
            
            $permissions = Role::findByName($role->name)->permissions;

            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';
            $role_id = $role->id;
            $numberOfProduct = DB::table('products')->where('is_active', true)->count();
            $custom_fields = CustomField::where([
                                ['belongs_to', 'product'],
                                ['is_table', true]
                            ])->pluck('name');
            $field_name = [];
            foreach($custom_fields as $fieldName) {
                $field_name[] = str_replace(" ", "_", strtolower($fieldName));
            }
            return view('backend.product.index', compact('warehouse_id', 'product_type', 'brand_id', 'category_id', 'unit_id', 'tax_id', 'imeiorvariant', 'all_permission', 'role_id', 'numberOfProduct', 'custom_fields', 'field_name','lims_warehouse_list', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function productData(Request $request)
    {
        $columns = array(
            1 => 'name',
            2 => 'code',
            3 => 'brand_id',
            4 => 'category_id',
            5 => 'qty',
            6 => 'unit_id',
            7 => 'price',
            8 => 'cost',
        );
        
        $filtered_data = [
            'warehouse_id' => $request->input('warehouse_id'),
            'product_type' => $request->input('product_type'),
            'brand_id' => $request->input('brand_id'),
            'category_id' => $request->input('category_id'),
            'unit_id' => $request->input('unit_id'),
            'tax_id' => $request->input('tax_id'),
            'is_imei' => $request->input('imeiorvariant') == 'imei' ? "1" : "0",
            'is_variant' => $request->input('imeiorvariant') == 'variant' ? "1" : "0",
        ];

        $totalData = DB::table('products')->where('is_active', true)->count();

        $warehouse_id = $request->input('warehouse_id');
        $is_recipe = $request->input('is_recipe');
        // dd($warehouse_id);
        if($is_recipe == true){
            $totalData = Product::where('is_recipe',1)->where('is_active', true)->count();
        }else{
            $totalData = DB::table('products')->where('is_active', true)->count();
        }

        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'products.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        //fetching custom fields data
        $custom_fields = CustomField::where([
                        ['belongs_to', 'product'],
                        ['is_table', true]
                    ])->pluck('name');
        $field_names = [];
        foreach($custom_fields as $fieldName) {
            $field_names[] = str_replace(" ", "_", strtolower($fieldName));
        }
        if(empty($request->input('search.value'))){
            $query = Product::with('category', 'brand', 'unit')->offset($start)
                        ->where('is_active', true)
                        ->limit($limit)
                        ->orderBy($order,$dir);
            $products = $is_recipe ? $query->where('is_recipe',1)->get() : $query->get();
        } else {
            $search = $request->input('search.value');
            $q = Product::select('products.*')
                ->with('category', 'brand', 'unit')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->leftjoin('product_purchases','product_purchases.product_id','=', 'products.id')
                ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                ->leftjoin('product_variants', 'products.id', '=', 'product_variants.product_id')
                ->where([
                    ['products.name', 'LIKE', "%{$search}%"],
                    ['products.is_active', true]
                ])
                ->orWhere([
                    ['products.code', 'LIKE', "%{$search}%"],
                    ['products.is_active', true]
                ])
                ->orWhere([
                    ['product_variants.item_code', 'LIKE', "%{$search}%"],
                    ['products.is_active', true]
                ])
                ->orWhere([
                    ['categories.name', 'LIKE', "%{$search}%"],
                    ['categories.is_active', true],
                    ['products.is_active', true]
                ])
                ->orWhere([
                    ['brands.title', 'LIKE', "%{$search}%"],
                    ['brands.is_active', true],
                    ['products.is_active', true]
                ])
                ->orWhere([
                    ['product_purchases.imei_number', 'LIKE', "%{$search}%"],
                    ['products.is_active', true]
                ]);
            //searching with custom field
            foreach ($field_names as $key => $field_name) {
                $q = $q->orwhere('products.' . $field_name, 'LIKE', "%{$search}%");
            }

            $q = $q->offset($start)
                ->limit($limit)
                ->orderBy($order,$dir);

            $products = $q->groupBy('products.id')->get();
            $products = $is_recipe ? $q->where('is_recipe',1)->get() : $q->get();
            $totalFiltered = $q->groupBy('products.id')->count();
            /*$totalFiltered = Product::
                            join('categories', 'products.category_id', '=', 'categories.id')
                            ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                            ->where([
                                ['products.name','LIKE',"%{$search}%"],
                                ['products.is_active', true]
                            ])
                            ->orWhere([
                                ['products.code', 'LIKE', "%{$search}%"],
                                ['products.is_active', true]
                            ])
                            ->orWhere([
                                ['categories.name', 'LIKE', "%{$search}%"],
                                ['categories.is_active', true],
                                ['products.is_active', true]
                            ])
                            ->orWhere([
                                ['brands.title', 'LIKE', "%{$search}%"],
                                ['brands.is_active', true],
                                ['products.is_active', true]
                            ])
                            ->count();*/
        }
        $data = array();
        if(!empty($products))
        {
            foreach ($products as $key=>$product)
            {
                $nestedData['id'] = $product->id;
                $nestedData['key'] = $key;
                $product_image = explode(",", $product->image);
                $product_image = htmlspecialchars($product_image[0]);
                if($product_image && $product_image != 'zummXD2dvAtI.png') {
                    if(file_exists("public/images/product/small/". $product_image))
                        $nestedData['image'] = '<img src="'.url('images/product/small', $product_image).'" height="50" width="50">';
                    else
                        $nestedData['image'] = '<img src="'.url('images/product', $product_image).'" height="50" width="50">';
                }
                else
                    $nestedData['image'] = '<img src="images/zummXD2dvAtI.png" height="50" width="50">';
                $nestedData['name'] = '<div class="d-flex align-items-center">'.$nestedData['image'].'  <span style="color:#111;margin:0 10px;">'.$product->name.'</span></div>';
                $nestedData['code'] = $product->code;
                if($product->brand)
                    $nestedData['brand'] = $product->brand->title;
                else
                    $nestedData['brand'] = "N/A";
                $nestedData['category'] = $product->category->name;
                if($filtered_data['warehouse_id'] > 0 && $product->type == 'standard') {
                    $nestedData['qty'] = Product_Warehouse::where([
                                                ['product_id', $product->id],
                                                ['warehouse_id', $filtered_data['warehouse_id']]
                                            ])->sum('qty');
                }
                elseif($product->type == 'standard') {
                    $nestedData['qty'] = Product_Warehouse::where([
                        ['product_id', $product->id],
                    ])->sum('qty');
                }
                else
                    $nestedData['qty'] = $product->qty;

                // 'product_type', 'brand_id', 'category_id', 'unit_id', 'tax_id'
                if ($filtered_data['product_type'] != 'all' && $filtered_data['product_type'] != $product->type) {
                    $nestedData['qty'] = 0;
                }
                if ($filtered_data['brand_id'] != '0' && $filtered_data['brand_id'] != $product->brand_id) {
                    $nestedData['qty'] = 0;
                }
                if ($filtered_data['category_id'] != '0' && $filtered_data['category_id'] != $product->category_id) {
                    $nestedData['qty'] = 0;
                }
                if ($filtered_data['unit_id'] != '0' && $filtered_data['unit_id'] != $product->unit_id) {
                    $nestedData['qty'] = 0;
                }
                if ($filtered_data['tax_id'] != '0' && $filtered_data['tax_id'] != $product->tax_id) {
                    $nestedData['qty'] = 0;
                }
                if ($filtered_data['is_imei'] != '0' && $filtered_data['is_imei'] != $product->is_imei) {
                    $nestedData['qty'] = 0;
                }
                if ($filtered_data['is_variant'] != '0' && $filtered_data['is_variant'] != $product->is_variant) {
                    $nestedData['qty'] = 0;
                }

                if($product->unit_id)
                    $nestedData['unit'] = $product->unit->unit_name ?? 'N/A';
                else
                    $nestedData['unit'] = 'N/A';

                if($product->type == 'combo'){
                    $nestedData['wastage_percent'] = $product->wastage_percent ?? 'N/A';
                    $combo_unit_id = $product->combo_unit_id ?? 'N/A';
                    $combo_unit_arr = explode(',', $combo_unit_id);
                    $units = Unit::whereIn('id', $combo_unit_arr)->pluck('unit_name', 'id')->toArray();
                    $combo_unit_names = array_map(function ($id) use ($units) {
                        return $units[$id] ?? '';
                    }, $combo_unit_arr);
                    $nestedData['combo_unit'] = implode(',', $combo_unit_names);
                }
                else{
                    $nestedData['combo_unit_id'] = 'N/A';
                }

                $nestedData['price'] = $product->price;
                $nestedData['cost'] = $product->cost;

                if(config('currency_position') == 'prefix')
                    $nestedData['stock_worth'] = config('currency').' '.($nestedData['qty'] * $product->price).' / '.config('currency').' '.($nestedData['qty'] * $product->cost);
                else
                    $nestedData['stock_worth'] = ($nestedData['qty'] * $product->price).' '.config('currency').' / '.($nestedData['qty'] * $product->cost).' '.config('currency');

                //fetching custom fields data
                foreach($field_names as $field_name) {
                    $nestedData[$field_name] = $product->$field_name;
                }

                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.__("db.action").'
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                            <li>
                                <button="type" class="btn btn-link view"><i class="fa fa-eye"></i> '.__('db.View').'</button>
                            </li>';

                if(in_array("products-edit", $request['all_permission']))
                    $nestedData['options'] .= '<li>
                            <a href="'.route('products.edit', $product->id).'" class="btn btn-link"><i class="fa fa-edit"></i> '.__('db.edit').'</a>
                        </li>';
                if(in_array("product_history", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => "products.history", "method" => "GET"] ).'
                            <li>
                                <input type="hidden" name="product_id" value="'.$product->id.'" />
                                <button type="submit" class="btn btn-link"><i class="dripicons-checklist"></i> '.__("db.Product History").'</button>
                            </li>'.\Form::close();
                if(in_array("print_barcode", $request['all_permission'])) {
                    $product_info = $product->code.' ('.$product->name.')';
                    $nestedData['options'] .= \Form::open(["route" => "product.printBarcode", "method" => "GET"] ).'
                        <li>
                            <input type="hidden" name="data" value="'.$product_info.'" />
                            <button type="submit" class="btn btn-link"><i class="dripicons-print"></i> '.__("db.print_barcode").'</button>
                        </li>'.\Form::close();
                }
                if(in_array("products-delete", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => ["products.destroy", $product->id], "method" => "DELETE"] ).'
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="fa fa-trash"></i> '.__("db.delete").'</button>
                            </li>'.\Form::close().'
                        </ul>
                    </div>';
                // data for product details by one click
                if($product->tax_id)
                    $tax = Tax::find($product->tax_id)->name ?? "N/A";
                else
                    $tax = "N/A";

                if($product->tax_method == 1)
                    $tax_method = __('db.Exclusive');
                else
                    $tax_method = __('db.Inclusive');

                $nestedData['product'] = array( '[ "'.$product->type.'"', ' "'.$product->name.'"', ' "'.$product->code.'"', ' "'.$nestedData['brand'].'"', ' "'.$nestedData['category'].'"', ' "'.$nestedData['unit'].'"', ' "'.$product->cost.'"', ' "'.$product->price.'"', ' "'.$tax.'"', ' "'.$tax_method.'"', ' "'.$product->alert_quantity.'"', ' "'.preg_replace('/\s+/S', " ", $product->product_details).'"', ' "'.$product->id.'"', ' "'.$product->product_list.'"', ' "'.$product->variant_list.'"', ' "'.$product->qty_list.'"', ' "'.$product->price_list.'"', ' "'.$nestedData['qty'].'"', ' "'.$product->image.'"', ' "'.$product->is_variant.'"','"'.@$nestedData['combo_unit'].'"','"'.@$nestedData['wastage_percent'].'"]'
                );
                //$nestedData['imagedata'] = DNS1D::getBarcodePNG($product->code, $product->barcode_symbology);
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
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('products-add')){
            $lims_product_list_without_variant = $this->productWithoutVariant();
            $lims_product_list_with_variant = $this->productWithVariant();
            $lims_brand_list = Brand::where('is_active', true)->get();
            $lims_category_list = Category::where('is_active', true)->get();
            $lims_unit_list = Unit::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $numberOfProduct = Product::where('is_active', true)->count();
            $custom_fields = CustomField::where('belongs_to', 'product')->get();

            $general_setting = DB::table('general_settings')->select('modules')->first();
            if(in_array('restaurant',explode(',',$general_setting->modules))){
                $kitchen_list = DB::table('kitchens')->where('is_active',1)->get();
                $menu_type_list = DB::table('menu_type')->where('is_active',1)->get();

                return view('backend.product.create',compact('kitchen_list','menu_type_list','lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_warehouse_list', 'numberOfProduct', 'custom_fields'));
            }

            return view('backend.product.create',compact('lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_warehouse_list', 'numberOfProduct', 'custom_fields'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    private function diffSizeOfImagePathExistOrCreate()
    {
        if (!file_exists(public_path("images/product/xlarge")) && !is_dir(public_path("images/product/xlarge"))) {
            mkdir(public_path("images/product/xlarge"), 0755, true);
        }
        if (!file_exists(public_path("images/product/large")) && !is_dir(public_path("images/product/large"))) {
            mkdir(public_path("images/product/large"), 0755, true);
        }
        if (!file_exists(public_path("images/product/medium")) && !is_dir(public_path("images/product/medium"))) {
            mkdir(public_path("images/product/medium"), 0755, true);
        }
        if (!file_exists(public_path("images/product/small")) && !is_dir(public_path("images/product/small"))) {
            mkdir(public_path("images/product/small"), 0755, true);
        }
    }

    private function diffSizeImageStore($image, string $imageName)
    {
        $image->resize(1000, 1250)->save(public_path('images/product/xlarge/' . $imageName));
        $image->resize(500, 500)->save(public_path('images/product/large/' . $imageName));
        $image->resize(250, 250)->save(public_path('images/product/medium/' . $imageName));
        $image->resize(100, 100)->save(public_path('images/product/small/' . $imageName));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'code' => [
                'max:255',
                    Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
            'name' => [
                'max:255',
                    Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ]
        ]);

        $data = $request->except('image', 'file');

        // handle warranty and guarantee
        if (!isset($data['warranty'])) {
            unset($data['warranty']);
            unset($data['warranty_type']);
        }
        if (!isset($data['guarantee'])) {
            unset($data['guarantee']);
            unset($data['guarantee_type']);
        }

        if(isset($data['is_variant'])) {
            $data['variant_option'] = json_encode(array_unique($data['variant_option']));
            $data['variant_value'] = json_encode(array_unique($data['variant_value']));
        }
        else {
            $data['variant_option'] = $data['variant_value'] = null;
        }

        $data['name'] = preg_replace('/[\n\r]/', "<br>", htmlspecialchars(trim($data['name']), ENT_QUOTES));

        if(in_array('ecommerce', explode(',',config('addons')))) {
            $data['slug'] = Str::slug($data['name'], '-');
            $data['slug'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['slug']);
            $data['slug'] = str_replace( '\/', '/', $data['slug'] );
        }

        if(in_array('restaurant', explode(',',config('addons')))) {
            $data['menu_type'] = implode(",", $request->menu_type);
        }


        if($data['type'] == 'combo' || (isset($data['is_recipe']) && $data['is_recipe'] == 1)) {

            $data['product_list'] = implode(",", $data['product_id']);
            $data['variant_list'] = implode(",", $data['variant_id']);
            $data['qty_list'] = implode(",", $data['product_qty']);
            $data['price_list'] = implode(",", $data['unit_price']);
            $data['wastage_percent'] = implode(",", $data['wastage_percent']);
            $data['combo_unit_id'] = implode(",", $data['combo_unit_id']);

            //$data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;
        }

        elseif($data['type'] == 'digital' || $data['type'] == 'service')
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;

        $data['product_details'] = str_replace('"', '@', $data['product_details']);

        if($data['starting_date'])
            $data['starting_date'] = date('Y-m-d', strtotime($data['starting_date']));
        if($data['last_date'])
            $data['last_date'] = date('Y-m-d', strtotime($data['last_date']));
        $data['is_active'] = true;
        $images = $request->image;
        $image_names = [];
        if ($images) {
            // Ensure the necessary directories exist using public_path()
            $this->diffSizeOfImagePathExistOrCreate();

            foreach ($images as $key => $image) {
                $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
                $imageName = date("Ymdhis") . ($key + 1);

                // Handle multi-tenant logic if necessary
                if (!config('database.connections.saleprosaas_landlord')) {
                    $imageName = $imageName . '.' . $ext;

                } else {
                    $imageName = $this->getTenantId() . '_' . $imageName . '.' . $ext;
                }


                $image->move(public_path('images/product'), $imageName);

                $manager = new ImageManager(new GdDriver());
                $image = $manager->read(public_path('images/product/' . $imageName));

                $this->diffSizeImageStore($image, $imageName);

                // Collect image names for saving in the database
                $image_names[] = $imageName;
            }

            // Save the image names in the database
            $data['image'] = implode(",", $image_names);
        }

        else {
            $data['image'] = 'zummXD2dvAtI.png';
        }
        $file = $request->file;
        if ($file) {
            $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            $fileName = strtotime(date('Y-m-d H:i:s'));
            $fileName = $fileName . '.' . $ext;
            $file->move(public_path('product/files'), $fileName);
            $data['file'] = $fileName;
        }
        if(!isset($data['is_sync_disable']) && \Schema::hasColumn('products', 'is_sync_disable'))
                $data['is_sync_disable'] = null;
        //return $data;

        $lims_product_data = Product::create($data);

        $custom_field_data = [];
        $custom_fields = CustomField::where('belongs_to', 'product')->select('name', 'type')->get();
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
            DB::table('products')->where('id', $lims_product_data->id)->update($custom_field_data);
        //dealing with initial stock and auto purchase
        $initial_stock = 0;
        if(isset($data['is_initial_stock']) && !isset($data['is_variant']) && !isset($data['is_batch'])) {
            foreach ($data['stock_warehouse_id'] as $key => $warehouse_id) {
                $stock = $data['stock'][$key];
                if($stock > 0) {
                    $this->autoPurchase($lims_product_data, $warehouse_id, $stock);
                    $initial_stock += $stock;
                }
            }
        }
        if($initial_stock > 0) {
            $lims_product_data->qty += $initial_stock;
            $lims_product_data->save();
        }
        //dealing with product variant
        if(!isset($data['is_batch']))
            $data['is_batch'] = null;
        $variant_ids = [];
        if(isset($data['is_variant'])) {
            foreach ($data['variant_name'] as $key => $variant_name) {
                $lims_variant_data = Variant::firstOrCreate(['name' => $data['variant_name'][$key]]);
                $variant_ids[] = $lims_variant_data->id;
                $product_variant = ProductVariant::firstOrNew([
                    'product_id' => $lims_product_data->id,
                    'variant_id' => $lims_variant_data->id,
                    'item_code' => $data['item_code'][$key],
                    'additional_cost' => $data['additional_cost'][$key],
                    'additional_price' => $data['additional_price'][$key],
                    'qty' => 0,
                ]);
                $product_variant->position = $key + 1;
                $product_variant->save();
            }
        }
        if(isset($data['is_diffPrice'])) {
            foreach ($data['diff_price'] as $key => $diff_price) {
                if($diff_price) {
                    Product_Warehouse::firstOrCreate([
                        "product_id" => $lims_product_data->id,
                        "warehouse_id" => $data["warehouse_id"][$key],
                        "qty" => 0,
                        "price" => $diff_price
                    ]);
                }
            }
        }
        elseif(!isset($data['is_initial_stock']) && !isset($data['is_batch']) && config('without_stock') == 'yes') {
            $warehouse_ids = Warehouse::where('is_active', true)->pluck('id');
            foreach ($warehouse_ids as $warehouse_id) {
                if(count($variant_ids)) {
                    foreach ($variant_ids as $variant_id) {
                        Product_Warehouse::firstOrCreate([
                            "product_id" => $lims_product_data->id,
                            "variant_id" => $variant_id,
                            "warehouse_id" => $warehouse_id,
                            "qty" => 0,
                        ]);
                    }
                }
                else {
                    Product_Warehouse::firstOrCreate([
                        "product_id" => $lims_product_data->id,
                        "warehouse_id" => $warehouse_id,
                        "qty" => 0,
                    ]);
                }
            }
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        \Session::flash('create_message', 'Product created successfully');
    }

    public function autoPurchase($product_data, $warehouse_id, $stock)
    {
        $data['reference_no'] = 'pr-' . date("Ymd") . '-'. date("his");
        $data['user_id'] = Auth::id();
        $data['warehouse_id'] = $warehouse_id;
        $data['item'] = 1;
        $data['total_qty'] = $stock;
        $data['total_discount'] = 0;
        $data['status'] = 1;
        $data['payment_status'] = 2;
        if($product_data->tax_id) {
            $tax_data = DB::table('taxes')->select('rate')->find($product_data->tax_id);
            if($product_data->tax_method == 1) {
                $net_unit_cost = number_format($product_data->cost, 2, '.', '');
                $tax = number_format($product_data->cost * $stock * ($tax_data->rate / 100), 2, '.', '');
                $cost = number_format(($product_data->cost * $stock) + $tax, 2, '.', '');
            }
            else {
                $net_unit_cost = number_format((100 / (100 + $tax_data->rate)) * $product_data->cost, 2, '.', '');
                $tax = number_format(($product_data->cost - $net_unit_cost) * $stock, 2, '.', '');
                $cost = number_format($product_data->cost * $stock, 2, '.', '');
            }
            $tax_rate = $tax_data->rate;
            $data['total_tax'] = $tax;
            $data['total_cost'] = $cost;
        }
        else {
            $data['total_tax'] = 0.00;
            $data['total_cost'] = number_format($product_data->cost * $stock, 2, '.', '');
            $net_unit_cost = number_format($product_data->cost, 2, '.', '');
            $tax_rate = 0.00;
            $tax = 0.00;
            $cost = number_format($product_data->cost * $stock, 2, '.', '');
        }

        $product_warehouse_data = Product_Warehouse::select('id', 'qty')
                                ->where([
                                    ['product_id', $product_data->id],
                                    ['warehouse_id', $warehouse_id]
                                ])->first();
        if($product_warehouse_data) {
            $product_warehouse_data->qty += $stock;
            $product_warehouse_data->save();
        }
        else {
            $lims_product_warehouse_data = new Product_Warehouse();
            $lims_product_warehouse_data->product_id = $product_data->id;
            $lims_product_warehouse_data->warehouse_id = $warehouse_id;
            $lims_product_warehouse_data->qty = $stock;
            $lims_product_warehouse_data->save();
        }
        $data['order_tax'] = 0;
        $data['grand_total'] = $data['total_cost'];
        $data['paid_amount'] = $data['grand_total'] ;
        //insetting data to purchase table
        $purchase_data = Purchase::create($data);
        //inserting data to product_purchases table
        ProductPurchase::create([
            'purchase_id' => $purchase_data->id,
            'product_id' => $product_data->id,
            'qty' => $stock,
            'recieved' => $stock,
            'purchase_unit_id' => $product_data->unit_id,
            'net_unit_cost' => $net_unit_cost,
            'discount' => 0,
            'tax_rate' => $tax_rate,
            'tax' => $tax,
            'total' => $cost
        ]);
        //inserting data to payments table
        Payment::create([
            'payment_reference' => 'ppr-' . date("Ymd") . '-'. date("his"),
            'user_id' => Auth::id(),
            'purchase_id' => $purchase_data->id,
            'account_id' => 0,
            'amount' => $data['grand_total'],
            'change' => 0,
            'paying_method' => 'Cash'
        ]);
    }

    public function history(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('product_history')) {
            if($request->input('warehouse_id'))
                $warehouse_id = $request->input('warehouse_id');
            else
                $warehouse_id = 0;

            if($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date = $request->input('ending_date');
            }
            else {
                $starting_date = date("Y-m-d", strtotime(date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d') )))));
                $ending_date = date("Y-m-d");
            }
            $product_id = $request->input('product_id');
            $product_data = Product::select('name', 'code')->find($product_id);
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            return view('backend.product.history',compact('starting_date', 'ending_date', 'warehouse_id', 'product_id', 'product_data', 'lims_warehouse_list'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function saleHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('sales')
            ->join('product_sales', 'sales.id', '=', 'product_sales.sale_id')
            ->where('product_sales.product_id', $product_id)
            ->whereDate('sales.created_at', '>=' ,$request->input('starting_date'))
            ->whereDate('sales.created_at', '<=' ,$request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('sales.user_id', Auth::id());

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'sales.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->join('customers', 'sales.customer_id', '=', 'customers.id')
                ->join('warehouses', 'sales.warehouse_id', '=', 'warehouses.id')
                ->select('sales.id', 'sales.reference_no', 'sales.created_at', 'customers.name as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_sales.qty', 'product_sales.sale_unit_id', 'product_sales.total')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $sales = $q->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = $q->whereDate('sales.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $sales =  $q->orwhere([
                                ['sales.reference_no', 'LIKE', "%{$search}%"],
                                ['sales.user_id', Auth::id()]
                            ])
                            ->get();
                $totalFiltered = $q->orwhere([
                                    ['sales.reference_no', 'LIKE', "%{$search}%"],
                                    ['sales.user_id', Auth::id()]
                                ])
                                ->count();
            }
            else {
                $sales =  $q->orwhere('sales.reference_no', 'LIKE', "%{$search}%")->get();
                $totalFiltered = $q->orwhere('sales.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($sales))
        {
            foreach ($sales as $key => $sale)
            {
                $nestedData['id'] = $sale->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($sale->created_at));
                $nestedData['reference_no'] = $sale->reference_no;
                $nestedData['warehouse'] = $sale->warehouse_name;
                $nestedData['customer'] = $sale->customer_name.' ['.($sale->customer_number).']';
                $nestedData['qty'] = number_format($sale->qty, config('decimal'));
                if($sale->sale_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($sale->sale_unit_id);
                    $nestedData['qty'] .= ' '.$unit_data->unit_code;
                }
                $nestedData['unit_price'] = number_format(($sale->total / $sale->qty), config('decimal'));
                $nestedData['sub_total'] = number_format($sale->total, config('decimal'));
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

    public function purchaseHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('purchases')
            ->join('product_purchases', 'purchases.id', '=', 'product_purchases.purchase_id')
            ->where('product_purchases.product_id', $product_id)
            ->whereDate('purchases.created_at', '>=' ,$request->input('starting_date'))
            ->whereDate('purchases.created_at', '<=' ,$request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('purchases.user_id', Auth::id());

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'purchases.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                ->join('warehouses', 'purchases.warehouse_id', '=', 'warehouses.id')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $purchases = $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = $q->whereDate('purchases.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $purchases =  $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')
                            ->orwhere([
                                ['purchases.reference_no', 'LIKE', "%{$search}%"],
                                ['purchases.user_id', Auth::id()]
                            ])->get();
                $totalFiltered = $q->orwhere([
                                    ['purchases.reference_no', 'LIKE', "%{$search}%"],
                                    ['purchases.user_id', Auth::id()]
                                ])->count();
            }
            else {
                $purchases =  $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')
                            ->orwhere('purchases.reference_no', 'LIKE', "%{$search}%")
                            ->get();
                $totalFiltered = $q->orwhere('purchases.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($purchases))
        {
            foreach ($purchases as $key => $purchase)
            {
                $nestedData['id'] = $purchase->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($purchase->created_at));
                $nestedData['reference_no'] = $purchase->reference_no;
                $nestedData['warehouse'] = $purchase->warehouse_name;
                if($purchase->supplier_id)
                    $nestedData['supplier'] = $purchase->supplier_name.' ['.($purchase->supplier_number).']';
                else
                    $nestedData['supplier'] = 'N/A';
                $nestedData['qty'] = number_format($purchase->qty, config('decimal'));
                if($purchase->purchase_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($purchase->purchase_unit_id);
                    $nestedData['qty'] .= ' '.$unit_data->unit_code;
                }
                $nestedData['unit_cost'] = number_format(($purchase->total / $purchase->qty), config('decimal'));
                $nestedData['sub_total'] = number_format($purchase->total, config('decimal'));
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

    public function saleReturnHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('returns')
            ->join('product_returns', 'returns.id', '=', 'product_returns.return_id')
            ->where('product_returns.product_id', $product_id)
            ->whereDate('returns.created_at', '>=' ,$request->input('starting_date'))
            ->whereDate('returns.created_at', '<=' ,$request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('returns.user_id', Auth::id());

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'returns.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->join('customers', 'returns.customer_id', '=', 'customers.id')
                ->join('warehouses', 'returns.warehouse_id', '=', 'warehouses.id')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $returnss = $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = $q->whereDate('returns.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $returnss =  $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')
                            ->orwhere([
                                ['returns.reference_no', 'LIKE', "%{$search}%"],
                                ['returns.user_id', Auth::id()]
                            ])
                            ->get();
                $totalFiltered = $q->orwhere([
                                    ['returns.reference_no', 'LIKE', "%{$search}%"],
                                    ['returns.user_id', Auth::id()]
                                ])
                                ->count();
            }
            else {
                $returnss =  $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')
                            ->orwhere('returns.reference_no', 'LIKE', "%{$search}%")
                            ->get();
                $totalFiltered = $q->orwhere('returns.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($returnss))
        {
            foreach ($returnss as $key => $returns)
            {
                $nestedData['id'] = $returns->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($returns->created_at));
                $nestedData['reference_no'] = $returns->reference_no;
                $nestedData['warehouse'] = $returns->warehouse_name;
                $nestedData['customer'] = $returns->customer_name.' ['.($returns->customer_number).']';
                $nestedData['qty'] = number_format($returns->qty, config('decimal'));
                if($returns->sale_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($returns->sale_unit_id);
                    $nestedData['qty'] .= ' '.$unit_data->unit_code;
                }
                $nestedData['unit_price'] = number_format(($returns->total / $returns->qty), config('decimal'));
                $nestedData['sub_total'] = number_format($returns->total, config('decimal'));
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

    public function purchaseReturnHistoryData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $product_id = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('return_purchases')
            ->join('purchase_product_return', 'return_purchases.id', '=', 'purchase_product_return.return_id')
            ->where('purchase_product_return.product_id', $product_id)
            ->whereDate('return_purchases.created_at', '>=' ,$request->input('starting_date'))
            ->whereDate('return_purchases.created_at', '<=' ,$request->input('ending_date'));
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('return_purchases.user_id', Auth::id());

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'return_purchases.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $q = $q->leftJoin('suppliers', 'return_purchases.supplier_id', '=', 'suppliers.id')
                ->join('warehouses', 'return_purchases.warehouse_id', '=', 'warehouses.id')
                ->select('return_purchases.id', 'return_purchases.reference_no', 'return_purchases.created_at', 'return_purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'purchase_product_return.qty', 'purchase_product_return.purchase_unit_id', 'purchase_product_return.total')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
        if(empty($request->input('search.value'))) {
            $return_purchases = $q->get();
        }
        else
        {
            $search = $request->input('search.value');
            $q = $q->whereDate('return_purchases.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))));

            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $return_purchases =  $q->orwhere([
                                        ['return_purchases.reference_no', 'LIKE', "%{$search}%"],
                                        ['return_purchases.user_id', Auth::id()]
                                    ])
                                    ->get();
                $totalFiltered = $q->orwhere([
                                    ['return_purchases.reference_no', 'LIKE', "%{$search}%"],
                                    ['return_purchases.user_id', Auth::id()]
                                ])
                                ->count();
            }
            else {
                $return_purchases =  $q->orwhere('return_purchases.reference_no', 'LIKE', "%{$search}%")->get();
                $totalFiltered = $q->orwhere('return_purchases.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($return_purchases))
        {
            foreach ($return_purchases as $key => $return_purchase)
            {
                $nestedData['id'] = $return_purchase->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($return_purchase->created_at));
                $nestedData['reference_no'] = $return_purchase->reference_no;
                $nestedData['warehouse'] = $return_purchase->warehouse_name;
                if($return_purchase->supplier_id)
                    $nestedData['supplier'] = $return_purchase->supplier_name.' ['.($return_purchase->supplier_number).']';
                else
                    $nestedData['supplier'] = 'N/A';
                $nestedData['qty'] = number_format($return_purchase->qty, config('decimal'));
                if($return_purchase->purchase_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($return_purchase->purchase_unit_id);
                    $nestedData['qty'] .= ' '.$unit_data->unit_code;
                }
                $nestedData['unit_cost'] = number_format(($return_purchase->total / $return_purchase->qty), config('decimal'));
                $nestedData['sub_total'] = number_format($return_purchase->total, config('decimal'));
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

    public function variantData($id)
    {
        if(Auth::user()->role_id > 2) {
            return ProductVariant::join('variants', 'product_variants.variant_id', '=', 'variants.id')
                ->join('product_warehouse', function($join) {
                    $join->on('product_variants.product_id', '=', 'product_warehouse.product_id');
                    $join->on('product_variants.variant_id', '=', 'product_warehouse.variant_id');
                })
                ->select('variants.name', 'product_variants.item_code', 'product_variants.additional_cost', 'product_variants.additional_price', 'product_warehouse.qty')
                ->where([
                    ['product_warehouse.product_id', $id],
                    ['product_warehouse.warehouse_id', Auth::user()->warehouse_id]
                ])
                ->orderBy('product_variants.position')
                ->get();
        }
        else {
            return ProductVariant::join('variants', 'product_variants.variant_id', '=', 'variants.id')
                ->select('variants.name', 'product_variants.item_code', 'product_variants.additional_cost', 'product_variants.additional_price', 'product_variants.qty')
                ->orderBy('product_variants.position')
                ->where('product_id', $id)
                ->get();
        }
    }

    public function edit($id)
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('products-edit')) {
            $lims_product_list_without_variant = $this->productWithoutVariant();
            $lims_product_list_with_variant = $this->productWithVariant();
            $lims_brand_list = Brand::where('is_active', true)->get();
            $lims_category_list = Category::where('is_active', true)->get();
            $lims_unit_list = Unit::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_product_data = Product::where('id', $id)->first();

            $cost  = (float) $lims_product_data->cost;
            $price = (float) $lims_product_data->price;
            $margin = (float) $lims_product_data->profit_margin;
            if ($cost > 0 && $price > 0) {
                $calculatedMargin = (($price - $cost) / $cost) * 100;

                if (round($calculatedMargin, 2) != round($margin, 2)) {
                    $lims_product_data->profit_margin = round($calculatedMargin, 2);
                }
            }

            if($lims_product_data->variant_option) {
                $lims_product_data->variant_option = json_decode($lims_product_data->variant_option);
                $lims_product_data->variant_value = json_decode($lims_product_data->variant_value);
            }
            $lims_product_variant_data = $lims_product_data->variant()->orderBy('position')->get();

            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $noOfVariantValue = 0;
            $custom_fields = CustomField::where('belongs_to', 'product')->get();

            $general_setting = DB::table('general_settings')->select('modules')->first();
            if(in_array('ecommerce', explode(',',$general_setting->modules))) {
                $product_arr = explode(',',$lims_product_data->related_products);
                $related_products = DB::table('products')->whereIn('id',$product_arr)->get();
                return view('backend.product.edit',compact('related_products','lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_product_data', 'lims_product_variant_data', 'lims_warehouse_list', 'noOfVariantValue', 'custom_fields'));
            }

            $general_setting = DB::table('general_settings')->select('modules')->first();
            if(in_array('restaurant',explode(',',$general_setting->modules))){
                $kitchen_list = DB::table('kitchens')->where('is_active',1)->get();
                $menu_type_list = DB::table('menu_type')->where('is_active',1)->get();

                $product_arr = explode(',',$lims_product_data->related_products);
                $related_products = DB::table('products')->whereIn('id',$product_arr)->get();

                $extra_arr = explode(',',$lims_product_data->extras);
                $extras = DB::table('products')->whereIn('id',$extra_arr)->get();

                return view('backend.product.edit',compact('kitchen_list','menu_type_list','related_products','extras','lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_product_data', 'lims_product_variant_data', 'lims_warehouse_list', 'noOfVariantValue', 'custom_fields'));
            }
            return view('backend.product.edit',compact('lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_product_data', 'lims_product_variant_data', 'lims_warehouse_list', 'noOfVariantValue', 'custom_fields'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function updateProduct(Request $request)
    {

        if(!env('USER_VERIFIED')) {
            return redirect()->back()->with('not_permitted', __('db.This feature is disable for demo!'));
        }

        DB::beginTransaction();
        try {
            $this->validate($request, [
                'name' => [
                    'max:255',
                    Rule::unique('products')->ignore($request->input('id'))->where(function ($query) {
                        return $query->where('is_active', 1);
                    }),
                ],

                'code' => [
                    'max:255',
                    Rule::unique('products')->ignore($request->input('id'))->where(function ($query) {
                        return $query->where('is_active', 1);
                    }),
                ]
            ]);

            $lims_product_data = Product::findOrFail($request->input('id'));
            $data = $request->except('image', 'file', 'prev_img');
            $data['name'] = htmlspecialchars(trim($data['name']), ENT_QUOTES);


            $general_setting = DB::table('general_settings')->select('modules')->first();
            if(in_array('ecommerce', explode(',',$general_setting->modules))) {
                $data['slug'] = Str::slug($data['name'], '-');
                $data['slug'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['slug']);
                $data['slug'] = str_replace( '\/', '/', $data['slug'] );
                $data['related_products'] = rtrim($request->products, ",");

                if(isset($request->in_stock))
                    $data['in_stock'] = $request->input('in_stock');
                else
                    $data['in_stock'] = 0;

                if(isset($request->is_online))
                    $data['is_online'] = $request->input('is_online');
                else
                    $data['is_online'] = 0;
            }

            if(in_array('restaurant', explode(',',$general_setting->modules))) {
                $data['slug'] = Str::slug($data['name'], '-');
                $data['slug'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['slug']);
                $data['slug'] = str_replace( '\/', '/', $data['slug'] );
                $data['related_products'] = rtrim($request->products, ",");
                $data['extras'] = rtrim($request->extras, ",");

                if(isset($request->is_online))
                    $data['is_online'] = $request->input('is_online');
                else
                    $data['is_online'] = 0;

                if(isset($request->is_addon))
                    $data['is_addon'] = $request->input('is_addon');
                else
                    $data['is_addon'] = 0;

                $data['kitchen_id'] = $request->kitchen_id;
                $data['menu_type'] = implode(",", $request->menu_type);
            }


            if($data['type'] == 'combo') {
                $data['product_list'] = implode(",", $data['product_id']);
                $data['variant_list'] = implode(",", $data['variant_id']);
                $data['qty_list'] = implode(",", $data['product_qty']);
                $data['price_list'] = implode(",", $data['unit_price']);
                $data['wastage_percent'] = implode(",", $data['wastage_percent']);
                $data['combo_unit_id'] = implode(",", $data['combo_unit_id']);
                //$data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;
            }
            elseif($data['type'] == 'digital' || $data['type'] == 'service')
                $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;

            if(!isset($data['featured']))
                $data['featured'] = 0;

            if(!isset($data['is_embeded']))
                $data['is_embeded'] = 0;

            if(!isset($data['promotion']))
                $data['promotion'] = null;

            if(!isset($data['is_batch']))
                $data['is_batch'] = null;

            if(!isset($data['is_imei']))
                $data['is_imei'] = null;

            if(!isset($data['is_sync_disable']) && \Schema::hasColumn('products', 'is_sync_disable'))
                $data['is_sync_disable'] = null;

            if(isset($data['short_description']))
                $data['short_description'] = $data['short_description'];
            $data['product_details'] = str_replace('"', '@', $data['product_details']);
            if($data['starting_date'])
                $data['starting_date'] = date('Y-m-d', strtotime($data['starting_date']));
            if($data['last_date'])
                $data['last_date'] = date('Y-m-d', strtotime($data['last_date']));

            $previous_images = [];
            //dealing with previous images
            if($request->prev_img) {
                foreach ($request->prev_img as $key => $prev_img) {
                    if(!in_array($prev_img, $previous_images))
                        $previous_images[] = $prev_img;
                }
                $lims_product_data->image = implode(",", $previous_images);
                $lims_product_data->save();
            }
            else {
                $lims_product_data->image = null;
                $lims_product_data->save();
            }

            //dealing with new images
            if ($request->image) {
                // Ensure the necessary directories exist using public_path()
                $this->diffSizeOfImagePathExistOrCreate();

                $images = $request->image;
                $image_names = [];
                $length = count(explode(",", $lims_product_data->image));

                foreach ($images as $key => $image) {
                    $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);

                    if (!config('database.connections.saleprosaas_landlord')) {
                        $imageName = date("Ymdhis") . ($length + $key + 1) . '.' . $ext;
                    } else {
                        $imageName = $this->getTenantId() . '_' . date("Ymdhis") . ($length + $key + 1) . '.' . $ext;
                    }

                    $image->move(public_path('images/product'), $imageName);

                    $manager = new ImageManager(new GdDriver());
                    $image = $manager->read(public_path('images/product/' . $imageName));

                    $this->diffSizeImageStore($image, $imageName);

                    $image_names[] = $imageName;
                }

                // Append or set the image field with the new image names
                if($lims_product_data->image)
                    $data['image'] = $lims_product_data->image. ',' . implode(",", $image_names);
                else
                    $data['image'] = implode(",", $image_names);
            }
            else
                $data['image'] = $lims_product_data->image;

            $file = $request->file;
            if ($file) {
                $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
                $fileName = strtotime(date('Y-m-d H:i:s'));
                $fileName = $fileName . '.' . $ext;
                $file->move(public_path('product/files'), $fileName);
                $data['file'] = $fileName;
            }

            $old_product_variant_ids = ProductVariant::where('product_id', $request->input('id'))->pluck('id')->toArray();
            $new_product_variant_ids = [];
            //dealing with product variant
            if(isset($data['is_variant'])) {
                if(isset($data['variant_option']) && isset($data['variant_value'])) {
                    $data['variant_option'] = json_encode(array_unique($data['variant_option']));
                    $data['variant_value'] = json_encode(array_unique($data['variant_value']));
                }
                foreach ($data['variant_name'] as $key => $variant_name) {
                    $lims_variant_data = Variant::firstOrCreate(['name' => $data['variant_name'][$key]]);
                    $lims_product_variant_data = ProductVariant::where([
                                                    ['product_id', $lims_product_data->id],
                                                    ['variant_id', $lims_variant_data->id]
                                                ])->first();
                    if($lims_product_variant_data) {
                        $lims_product_variant_data->update([
                            'position' => $key+1,
                            'item_code' => $data['item_code'][$key],
                            'additional_cost' => $data['additional_cost'][$key],
                            'additional_price' => $data['additional_price'][$key]
                        ]);
                    }
                    else {
                        $lims_product_variant_data = ProductVariant::firstOrNew([
                            'product_id' => $lims_product_data->id,
                            'variant_id' => $lims_variant_data->id,
                            'item_code' => $data['item_code'][$key],
                            'additional_cost' => $data['additional_cost'][$key],
                            'additional_price' => $data['additional_price'][$key],
                            'qty' => 0,
                        ]);
                        $lims_product_variant_data->position = $key + 1;
                        $lims_product_variant_data->save();
                    }

                    $product_warehouses = Product_Warehouse::where([
                        'product_id' => $lims_product_data->id,
                        'variant_id' => $lims_variant_data->id
                    ])->get();
                    // if ($variant_name == 'Tell') {
                    //     DB::rollback();
                    //     return dd($product_warehouses->isEmpty());
                    // }
                    if ($product_warehouses->isEmpty()) {
                        $warehouse_ids = Warehouse::pluck('id')->toArray();
                        foreach ($warehouse_ids as $w_id) {
                            Product_Warehouse::firstOrCreate([
                                "product_id" => $lims_product_data->id,
                                "variant_id" => $lims_variant_data->id,
                                "warehouse_id" => $w_id,
                                "qty" => 0,
                            ]);
                        }
                    }

                    $new_product_variant_ids[] = $lims_product_variant_data->id;
                }
            }
            else {
                $data['is_variant'] = null;
                $data['variant_option'] = null;
                $data['variant_value'] = null;
            }
            //deleting old product variant if not exist
            foreach ($old_product_variant_ids as $key => $product_variant_id) {
                if (!in_array($product_variant_id, $new_product_variant_ids)) {
                    $productVariant = ProductVariant::find($product_variant_id);
                    if ($productVariant->qty > 0) {
                        DB::rollBack();
                        // return dd($productVariant);
                        return redirect()->back()->with('not_permitted', __('db.This variant has a quantity; you cannot delete it'));
                    }
                    Product_Warehouse::where('product_id', $productVariant->product_id)
                        ->where('variant_id', $productVariant->variant_id)
                        ->delete();

                    $productVariant->delete();
                }
            }

            if(isset($data['is_diffPrice'])) {
                foreach ($data['diff_price'] as $key => $diff_price) {
                    if($diff_price) {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $data['warehouse_id'][$key])->first();
                        if($lims_product_warehouse_data) {
                            $lims_product_warehouse_data->price = $diff_price;
                            $lims_product_warehouse_data->save();
                        }
                        else {
                            Product_Warehouse::firstOrCreate([
                                "product_id" => $lims_product_data->id,
                                "warehouse_id" => $data["warehouse_id"][$key],
                                "qty" => 0,
                                "price" => $diff_price
                            ]);
                        }
                    }
                }
            }
            else {
                $data['is_diffPrice'] = false;
                if(isset($data['warehouse_id'])){
                    foreach ($data['warehouse_id'] as $key => $warehouse_id) {
                        $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $warehouse_id)->first();
                        if($lims_product_warehouse_data) {
                            $lims_product_warehouse_data->price = null;
                            $lims_product_warehouse_data->save();
                        }
                    }
                }
            }
            // handle warranty and guarantee
            if (!isset($data['warranty'])) {
                $data['warranty'] = null;
                $data['warranty_type'] = null;
            }
            if (!isset($data['guarantee'])) {
                $data['guarantee'] = null;
                $data['guarantee_type'] = null;
            }
            $lims_product_data->update($data);
            //inserting data for custom fields
            $custom_field_data = [];
            $custom_fields = CustomField::where('belongs_to', 'product')->select('name', 'type')->get();
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
                DB::table('products')->where('id', $lims_product_data->id)->update($custom_field_data);
            $this->cacheForget('product_list');
            $this->cacheForget('product_list_with_variant');

            DB::commit();

            \Session::flash('edit_message', 'Product updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('not_permitted', __('db.Failed to update product Please try again'));
        }
    }

    public function generateCode()
    {
        $id = Keygen::numeric(8)->generate();
        return $id;
    }

    public function search(Request $request)
    {
        $product_code = explode(" (", $request['data']);
        $lims_product_data = Product::where('code', $product_code[0])->first();

        $product[] = $lims_product_data->name;
        $product[] = $lims_product_data->code;
        $product[] = $lims_product_data->qty;
        $product[] = $lims_product_data->price;
        $product[] = $lims_product_data->id;
        return $product;
    }

    public function saleUnit($id)
    {
        $unit = Unit::where("base_unit", $id)->orWhere('id', $id)->pluck('unit_name','id');
        return json_encode($unit);
    }

    public function getData($id, $variant_id)
    {

        if($variant_id) {
            $data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.name', 'product_variants.item_code')
                ->where([
                    ['products.id', $id],
                    ['product_variants.variant_id', $variant_id]
                ])->first();
            $data->code = $data->item_code;
        }
        else
            $data = Product::select('name', 'code')->find($id);
        return $data;
    }

    public function productWarehouseData($id)
    {
        $warehouse = [];
        $qty = [];
        $batch = [];
        $expired_date = [];
        $imei_number = [];
        $warehouse_name = [];
        $variant_name = [];
        $variant_qty = [];
        $product_warehouse = [];
        $product_variant_warehouse = [];
        $lims_product_data = Product::select('id', 'is_variant')->find($id);
        if($lims_product_data->is_variant) {
            $lims_product_variant_warehouse_data = Product_Warehouse::where('product_id', $lims_product_data->id)->orderBy('warehouse_id')->get();
            $lims_product_warehouse_data = Product_Warehouse::select('warehouse_id', DB::raw('sum(qty) as qty'))->where('product_id', $id)->groupBy('warehouse_id')->get();
            foreach ($lims_product_variant_warehouse_data as $key => $product_variant_warehouse_data) {
                $lims_warehouse_data = Warehouse::find($product_variant_warehouse_data->warehouse_id);
                $lims_variant_data = Variant::find($product_variant_warehouse_data->variant_id);
                $warehouse_name[] = $lims_warehouse_data->name;
                $variant_name[] = $lims_variant_data->name;
                $variant_qty[] = $product_variant_warehouse_data->qty;
            }
        }
        else {
            $lims_product_warehouse_data = Product_Warehouse::where('product_id', $id)->orderBy('warehouse_id', 'asc')->get();
        }
        foreach ($lims_product_warehouse_data as $key => $product_warehouse_data) {
            $lims_warehouse_data = Warehouse::find($product_warehouse_data->warehouse_id);
            if($product_warehouse_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no', 'expired_date')->find($product_warehouse_data->product_batch_id);
                $batch_no = $product_batch_data->batch_no;
                $expiredDate = date(config('date_format'), strtotime($product_batch_data->expired_date));
            }
            else {
                $batch_no = 'N/A';
                $expiredDate = 'N/A';
            }
            $warehouse[] = $lims_warehouse_data->name;
            $batch[] = $batch_no;
            $expired_date[] = $expiredDate;
            $qty[] = $product_warehouse_data->qty;

            $imeis = Product_Warehouse::select('imei_number')
                ->where('product_id', $lims_product_data->id)
                ->where('warehouse_id', $product_warehouse_data->warehouse_id)
                ->whereNotNull('imei_number')
                ->get();

            if($product_warehouse_data->imei_number && !str_contains($product_warehouse_data->imei_number, 'null')) {
                $imei_number[$key] = $product_warehouse_data->imei_number;
            }

            foreach ($imeis as $imei) {
                if (isset($imei->imei_number)){
                    $imei_number[$key] = isset($imei_number[$key]) ?  $imei_number[$key] . ',' . $imei->imei_number : $imei->imei_number;
                }
            }
            if (!isset($imei_number[$key])) {
                $imei_number[$key] = 'N/A';
            }
        }

        // remove duplication in imei_numbers
        if (isset($imei_number)) {
            for ($i = 0; $i < count($imei_number); $i++) {
                $temp = array_unique(explode(',', $imei_number[$i]));
                $imei_number[$i] = implode(',', $temp);
            }
        }

        $product_warehouse = [$warehouse, $qty, $batch, $expired_date, $imei_number];
        $product_variant_warehouse = [$warehouse_name, $variant_name, $variant_qty];
        return ['product_warehouse' => $product_warehouse, 'product_variant_warehouse' => $product_variant_warehouse];
    }

    public function printBarcode(Request $request)
    {
        if($request->input('data')) {
            $preLoadedproducts = $this->limsProductSearch($request);
        }
        else
            $preLoadedproducts = [];

        $lims_product_list_without_variant = $this->productWithoutVariant();
        $lims_product_list_with_variant = $this->productWithVariant();

        $barcode_settings = Barcode::select(DB::raw('CONCAT(name, ", ", COALESCE(description, "")) as name, id, is_default'))->get();
        $default = $barcode_settings->where('is_default', 1)->first();
        $barcode_settings = $barcode_settings->pluck('name', 'id');

        return view('backend.product.print_barcode', compact('barcode_settings','lims_product_list_without_variant', 'lims_product_list_with_variant', 'preLoadedproducts'));
    }

    public function productWithoutVariant()
    {
        return Product::ActiveStandard()->select('id', 'name', 'code')
                ->whereNull('is_variant')->get();
    }

    public function productWithVariant()
    {
        return Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->ActiveStandard()
                ->whereNotNull('is_variant')
                ->select('products.id', 'products.name', 'product_variants.item_code', 'product_variants.qty')
                ->orderBy('position')->get();
    }

    public function limsProductSearch(Request $request)
    {
        $product_code = explode("(", $request['data']);
        $product_code[0] = rtrim($product_code[0], " ");
        $lims_product_list = Product::where([
            ['code', $product_code[0] ],
            ['is_active', true]
        ])->get();
        if(count($lims_product_list) == 0) {
            $lims_product_list = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.*', 'product_variants.item_code', 'product_variants.variant_id', 'product_variants.additional_price')
                ->where('product_variants.item_code', $product_code[0])
                ->get();
        }
        elseif($lims_product_list[0]->is_variant) {
            $lims_product_list = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.*', 'product_variants.item_code', 'product_variants.variant_id', 'product_variants.additional_price')
                ->where('product_variants.product_id', $lims_product_list[0]->id)
                ->get();
        }
        foreach($lims_product_list as $lims_product_data) {
            $product = [];
            $product[] = $lims_product_data->name;
            if($lims_product_data->is_variant) {
                $product[] = $lims_product_data->item_code;
                $variant_id = $lims_product_data->variant_id;
                $additional_price = $lims_product_data->additional_price;
            }
            else {
                $product[] = $lims_product_data->code;
                $variant_id = '';
                $additional_price = 0;
            }

            // adding brand name
            $brand = Brand::find($lims_product_data->brand_id);

            $product[] = $lims_product_data->price + $additional_price;
            $product[] = DNS1D::getBarcodePNG($product[1], $lims_product_data->barcode_symbology);
            $product[] = $lims_product_data->promotion_price;
            $product[] = config('currency');
            $product[] = config('currency_position');
            $product[] = $lims_product_data->qty;
            $product[] = $lims_product_data->id;
            $product[] = $variant_id;
            $product[] = $lims_product_data->cost;
            $product[] = $brand->title ?? 'N/A';
            $product[] = $lims_product_data->unit_id ?? 'N/A';
            $unit = Unit::query()->where('id',$lims_product_data->unit_id)->orWhere('base_unit',$lims_product_data->unit_id)->get()->unique('id') ?? 'N/A';
            $unitOptions = '';
            foreach ($unit as $row) {
                $selected = $lims_product_data->unit_id == $row->id ? 'selected' : '';
                $unitOptions .= '<option value="'.$row->id.'" data-operation_value="'.$row->operation_value.'" data-operator="'.$row->operator.'" '.$selected.'>'.$row->unit_name.'</option>';
            }

            $product[] = '
                <select name="combo_unit_id[]" class="btn btn-outline-secondary form-control combo_unit_id"  onchange="calculate_price()">
                    '.$unitOptions.'
                </select>
            ';

            $products[] = $product;
        }
        return $products;
    }

    /*public function getBarcode()
    {
        return DNS1D::getBarcodePNG('72782608', 'C128');
    }*/

    public function checkBatchAvailability($product_id, $batch_no, $warehouse_id)
    {
        $product_batch_data = ProductBatch::where([
            ['product_id', $product_id],
            ['batch_no', $batch_no]
        ])->first();
        if($product_batch_data) {
            $product_warehouse_data = Product_Warehouse::select('qty')
            ->where([
                ['product_batch_id', $product_batch_data->id],
                ['warehouse_id', $warehouse_id]
            ])->first();
            if($product_warehouse_data) {
                $data['qty'] = $product_warehouse_data->qty;
                $data['product_batch_id'] = $product_batch_data->id;
                $data['expired_date'] = date(config('date_format'), strtotime($product_batch_data->expired_date));
                $data['message'] = 'ok';
            }
            else {
                $data['qty'] = 0;
                $data['message'] = 'This Batch does not exist in the selected warehouse!';
            }
        }
        else {
            $data['message'] = 'Wrong Batch Number!';
        }
        return $data;
    }

    public function importProduct(Request $request)
    {
        // Get file
        $upload = $request->file('file');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if ($ext != 'csv') {
            return redirect()->back()->with('message', __('db.Please upload a valid CSV file'));
        }

        $filePath = $upload->getRealPath();

        // Open and read file
        $file = fopen($filePath, 'r');
        $header = fgetcsv($file);
        if (!$header) {
            fclose($file);
            return redirect()->back()->with('message', __('db.CSV file is empty or invalid'));
        }

        $escapedHeader = [];
        foreach ($header as $key => $value) {
            $lheader = strtolower(trim($value));
            $escapedItem = preg_replace('/[^a-z]/', '', $lheader);
            $escapedHeader[] = $escapedItem;
        }

        // Looping through other columns
        try {
            DB::beginTransaction();
            $counter = 1;
            while ($columns = fgetcsv($file)) {
                if (count($escapedHeader) !== count($columns)) {
                    fclose($file);
                    throw new \Exception(__('db.CSV file format is incorrect'));
                }

                $data = array_combine($escapedHeader, $columns);

                // Validate and sanitize input
                $data['name'] = htmlspecialchars(trim($data['name']));
                $data['cost'] = is_numeric($data['cost']) ? str_replace(",", "", $data['cost']) : 0;

                // Default margin from general settings
                $general_setting = GeneralSetting::first();
                $defaultMargin = $general_setting->default_margin_value ?? 25;

                if (!empty($data['profitmargin']) && !empty($data['price'])) {
                    $data['price'] = is_numeric($data['price']) ? str_replace(",", "", $data['price']) : 0;

                    $data['profitmargin'] = ($data['cost'] > 0) ? (($data['price'] - $data['cost']) / $data['cost']) * 100 : $defaultMargin;
                } else if (!empty($data['profitmargin'])) {
                    $profitMargin = (float) $data['profitmargin'];

                    $data['price'] = $data['cost'] * (1 + $profitMargin / 100);
                } else if (!empty($data['price'])) {
                    $data['price'] = is_numeric($data['price']) ? str_replace(",", "", $data['price']) : 0;

                    $data['profitmargin'] = ($data['cost'] > 0) ? (($data['price'] - $data['cost']) / $data['cost']) * 100 : $defaultMargin;
                } else {
                    $data['profitmargin'] = $defaultMargin;
                    $data['price'] = $data['cost'] * (1 + $defaultMargin / 100);
                }

                // Handle brand
                $brand_id = null;
                if (isset($data['brand']) && $data['brand'] !== 'N/A' && $data['brand'] !== '') {
                    $lims_brand_data = Brand::firstOrCreate(['title' => $data['brand'], 'is_active' => true]);
                    $brand_id = $lims_brand_data->id;
                }

                // Handle category
                $lims_category_data = Category::firstOrCreate(['name' => $data['category'], 'is_active' => true]);

                // Handle unit
                $lims_unit_data = Unit::where('unit_code', $data['unitcode'])->first();
                if (!$lims_unit_data) {
                    fclose($file);
                    throw new \Exception(__('db.Unit code does not exist in the database'));
                }

                // Create or update product
                $product = Product::firstOrNew([
                    'name' => $data['name'],
                    'is_active' => true
                ]);

                $product->fill([
                    'code' => $data['code'],
                    'type' => strtolower($data['type']),
                    'barcode_symbology' => 'C128',
                    'brand_id' => $brand_id,
                    'category_id' => $lims_category_data->id,
                    'unit_id' => $lims_unit_data->id,
                    'purchase_unit_id' => $lims_unit_data->id,
                    'sale_unit_id' => $lims_unit_data->id,
                    'cost' => $data['cost'],
                    'profit_margin' => $data['profitmargin'],
                    'price' => $data['price'],
                    'tax_method' => 1,
                    'qty' => 0,
                    'product_details' => $data['productdetails'] ?? '',
                    'is_active' => true,
                    'image' => $data['image'] ?? 'zummXD2dvAtI.png',
                ]);

                if (in_array('ecommerce', explode(',', config('addons')))) {
                    $data['slug'] = Str::slug($data['name'], '-');
                    $product->slug = preg_replace('/[^A-Za-z0-9\-]/', '', $data['slug']);
                    $product->in_stock = true;
                }

                $image_names = [];
                if (!empty($data['image']) && $data['image'] != 'zummXD2dvAtI.png') {
                    $imageUrls = explode(',', $data['image']);
                    $this->diffSizeOfImagePathExistOrCreate();

                    foreach ($imageUrls as $url) {
                        $url = trim($url);
                        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                            Log::error("Skipping invalid URL: " . $url);
                            continue;
                        }

                        try {
                            $response = Http::get($url);
                            if (!$response->successful()) {
                                throw new Exception("Failed to fetch the image: {$url}");
                            }

                            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                            $imageName = date("Ymdhis") . uniqid() . '.' . $ext;

                            $manager = new ImageManager();
                            $image = $manager->make($response->body());

                            $image->save(public_path('images/product/') . $imageName);
                            $image->fit(1000, 1250)->save(public_path('images/product/xlarge/') . $imageName, 100);
                            $image->fit(500, 500)->save(public_path('images/product/large/') . $imageName, 100);
                            $image->fit(250, 250)->save(public_path('images/product/medium/') . $imageName, 100);
                            $image->fit(100, 100)->save(public_path('images/product/small/') . $imageName, 100);

                            $image_names[] = $imageName;
                        } catch (\Exception $e) {
                            throw new \Exception("Error downloading image: " . $e->getMessage());
                        }
                    }

                    $product['image'] = implode(",", $image_names);
                }

                $product->save();

                // Handle variants
                $warehouse_ids = Warehouse::where('is_active', true)->pluck('id');
                if (!empty($data['variantvalue']) && !empty($data['variantname'])) {
                    $variant_option = [];
                    $variant_value = [];
                    $variantInfo = explode(",", $data['variantvalue']);

                    foreach ($variantInfo as $key => $info) {
                        if (!strpos($info, "[")) {
                            fclose($file);
                            throw new \Exception(__('db.Invalid variant value format'));
                        }
                        $variant_option[] = strtok($info, "[");
                        $variant_value[] = str_replace("/", ",", substr($info, strpos($info, "[") + 1, (strpos($info, "]") - strpos($info, "[") - 1)));
                    }

                    $product->variant_option = json_encode($variant_option);
                    $product->variant_value = json_encode($variant_value);
                    $product->is_variant = true;
                    $product->save();

                    $variant_names = explode(",", $data['variantname']);
                    $item_codes = explode(",", $data['itemcode']);
                    $additional_costs = explode(",", $data['additionalcost']);
                    $additional_prices = explode(",", $data['additionalprice']);

                    $productVariants = [];
                    $productWarehouses = [];

                    foreach ($variant_names as $key => $variant_name) {
                        $variant = Variant::firstOrCreate(['name' => $variant_name]);

                        $productVariants[] = [
                            'product_id' => $product->id,
                            'variant_id' => $variant->id,
                            'position' => $key + 1,
                            'item_code' => $item_codes[$key] ?? $variant_name . '-' . $data['code'],
                            'additional_cost' => $additional_costs[$key] ?? 0,
                            'additional_price' => $additional_prices[$key] ?? 0,
                            'qty' => 0,
                        ];

                        foreach ($warehouse_ids as $warehouse_id) {
                            $productWarehouses[] = [
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'warehouse_id' => $warehouse_id,
                                'qty' => 0,
                            ];
                        }
                    }

                    ProductVariant::insert($productVariants);
                    if (config('without_stock') === 'yes') {
                        Product_Warehouse::insert($productWarehouses);
                    }
                } elseif (config('without_stock') === 'yes') {
                    $productWarehouses = [];
                    foreach ($warehouse_ids as $warehouse_id) {
                        $productWarehouses[] = [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse_id,
                            'qty' => 0,
                        ];
                    }
                    Product_Warehouse::insert($productWarehouses);
                }
                $counter++;
            }

            fclose($file);
            $this->cacheForget('product_list');
            $this->cacheForget('product_list_with_variant');
            DB::commit();
            return redirect('products')->with('import_message', __('db.Products imported successfully!'));
        } catch (\Exception $e) {
            // fclose($file);
            DB::rollBack();
            return redirect()->back()->with('not_permitted', "Error in row $counter: " . $e->getMessage());
        }
    }

    public function allProductInStock()
    {
        if(!in_array('ecommerce',explode(',', config('addons'))))
            return redirect()->back()->with('not_permitted', __('db.Please install the ecommerce addon!'));
        Product::where('is_active', true)->update(['in_stock' => true]);
        return redirect()->back()->with('create_message', __('db.All Products set to in stock successfully!'));
    }

    public function showAllProductOnline()
    {
        if(!in_array('ecommerce',explode(',', config('addons'))))
            return redirect()->back()->with('not_permitted', __('db.Please install the ecommerce addon!'));
        Product::where('is_active', true)->update(['is_online' => true]);
        return redirect()->back()->with('create_message', __('db.All Products will be showed to online!'));
    }

    private function deleteImageFromStorage($image)
    {
        $this->fileDelete(public_path('images/product/'), $image);
        $this->fileDelete(public_path('images/product/xlarge/'), $image);
        $this->fileDelete(public_path('images/product/large/'), $image);
        $this->fileDelete(public_path('images/product/medium/'), $image);
        $this->fileDelete(public_path('images/product/small/'), $image);
    }

    public function deleteBySelection(Request $request)
    {
        $product_id = $request['productIdArray'];
        foreach ($product_id as $id) {
            $lims_product_data = Product::findOrFail($id);
            $lims_product_data->is_active = false;
            $lims_product_data->save();

            if($lims_product_data->image) {
                $images = explode(",", $lims_product_data->image);
                foreach ($images as $image) {
                    $this->deleteImageFromStorage($image);
                }
            }
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return 'Product deleted successfully!';
    }

    public function destroy($id)
    {
        if(!env('USER_VERIFIED')) {
            return redirect()->back()->with('not_permitted', __('db.This feature is disable for demo!'));
        }
        else {
            $lims_product_data = Product::findOrFail($id);
            $lims_product_data->is_active = false;
            if($lims_product_data->image != 'zummXD2dvAtI.png') {
                $images = explode(",", $lims_product_data->image);
                foreach ($images as $key => $image) {
                    $this->deleteImageFromStorage($image);
                }
            }
            $lims_product_data->save();
            $this->cacheForget('product_list');
            $this->cacheForget('product_list_with_variant');
            // return redirect('products')->with('message', __('db.Product deleted successfully'));
            return redirect()->back()->with('message', __('db.Product deleted successfully'));
        }
    }

    public function getProductPrice($id)
    {
        $lims_product_data = Product::where('id', $id)->select('price')->first();

        return $lims_product_data;
    }

}
