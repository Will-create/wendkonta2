@extends('backend.layout.main')
@section('content')

<style type="text/css">
    .btn-icon i{margin-right:5px}
    .top-fields{margin-top:10px;position: relative;}
    .top-fields label {background:#FFF;font-size:11px;font-weight:600;margin-left:10px;padding:0 3px;position:absolute;top:-8px;z-index:9;}
    .top-fields input{font-size:13px;height:45px}
</style>

<x-success-message key="create_message" />
<x-success-message key="import_message" />
<x-error-message key="not_permitted" />
<x-error-message key="message" />

<section>
    <div class="container-fluid">
        @if(in_array("products-add", $all_permission))
            <a href="{{route('products.create')}}" class="btn btn-info add-product-btn btn-icon"><i class="dripicons-plus"></i> {{__('db.add_product')}}</a>
            <a href="#" data-toggle="modal" data-target="#importProduct" class="btn btn-primary add-product-btn btn-icon"><i class="dripicons-copy"></i> {{__('db.import_product')}}</a>

        @endif
        @if( in_array("products-edit", $all_permission) && in_array('ecommerce',explode(',',$general_setting->modules)) )
            <a href="{{route('product.allProductInStock')}}" class="btn btn-dark add-product-btn btn-icon"><i class="dripicons-stack"></i> {{__('db.All Product In Stock')}}</a>
            <a href="{{route('product.showAllProductOnline')}}" class="btn btn-dark add-product-btn btn-icon"><i class="dripicons-wifi"></i> {{__('db.Show All Product Online')}}</a>
        @endif
        <button type="button" class="btn btn-warning btn-icon" id="toggle-filter">
            <i class="dripicons-experiment"></i> {{ __('db.Filter Products') }}
        </button>

        <div class="card mt-3 mb-2">
            <div class="card-body" id="filter-card" style="display: none;">
                {!! Form::open(['route' => 'products.index', 'method' => 'get']) !!}
                <div class="row">
                    <div class="col-md-3 @if(\Auth::user()->role_id > 2){{'d-none'}}@endif">
                        <div class="form-group top-fields">
                            <label>{{__('db.Warehouse')}}</label>
                            <select id="warehouse_id" name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" >
                                <option value="0">{{__('db.All Warehouse')}}</option>
                                @foreach($lims_warehouse_list as $warehouse)
                                    <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group top-fields">
                            <label>{{__('db.Product Type')}}</label>
                            <select name="product_type" required class="form-control selectpicker" id="product_type" data-live-search="true" data-live-search-style="begins">
                                <option value="all" selected>All Types</option>
                                <option value="standard">Standard</option>
                                <option value="combo">Combo</option>
                                <option value="digital">Digital</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group top-fields">
                            <label>{{__('db.Brand')}}</label>
                            <select name="brand_id" required class="form-control selectpicker" id="brand_id" data-live-search="true" data-live-search-style="begins">
                                <option value="0" selected>All Brands</option>
                                @foreach($lims_brand_list as $brand)
                                    <option value="{{$brand->id}}">{{$brand->title}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group top-fields">
                            <label>{{__('db.category')}}</label>
                            <select name="category_id" required class="form-control selectpicker" id="category_id" data-live-search="true" data-live-search-style="begins">
                                <option value="0" selected>All Categories</option>
                                @foreach($lims_category_list as $category)
                                    <option value="{{$category->id}}">{{$category->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group top-fields">
                            <label>{{__('db.Unit')}}</label>
                            <select name="unit_id" required class="form-control selectpicker" id="unit_id" data-live-search="true" data-live-search-style="begins">
                                <option value="0" selected>All Unit</option>
                                @foreach($lims_unit_list as $unit)
                                    <option value="{{$unit->id}}">{{$unit->unit_name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group top-fields">
                            <label>{{__('db.Tax')}}</label>
                            <select name="tax_id" required class="form-control selectpicker" id="tax_id" data-live-search="true" data-live-search-style="begins">
                                <option value="0" selected>All Tax</option>
                                @foreach($lims_tax_list as $tax)
                                    <option value="{{$tax->id}}">{{$tax->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group top-fields">
                            <label>{{__('db.Product with')}}</label>
                            <select name="imeiorvariant" required class="form-control selectpicker" id="imeiorvariant">
                                <option value="0" selected>Select IMEI/Variant</option>
                                <option value="imei">IMEI</option>
                                <option value="variant">Variant</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 mt-1">
                        <div class="form-group">
                            <button class="btn btn-primary" id="filter-btn" type="submit">{{__('db.submit')}}</button>
                        </div>
                    </div>
                </div>
                
                {!! Form::close() !!}
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table id="product-data-table" class="table pt-0" style="width: 100%">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{__('db.product')}}</th>
                    <th>{{__('db.Code')}}</th>
                    <th>{{__('db.Brand')}}</th>
                    <th>{{__('db.category')}}</th>
                    <th>{{__('db.Quantity')}}</th>
                    <th>{{__('db.Unit')}}</th>
                    <th>{{__('db.Price')}}</th>
                    <th>{{__('db.Cost')}}</th>
                    <th>{{__('db.Stock Worth') . '(' . __('db.Price') . '/' . __('db.Cost') . ')'}}</th>
                    @foreach($custom_fields as $fieldName)
                        <th>{{$fieldName}}</th>
                    @endforeach
                    <th class="not-exported">{{__('db.action')}}</th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<div id="importProduct" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
      <div class="modal-content">
        {!! Form::open(['route' => 'product.import', 'method' => 'post', 'files' => true]) !!}
        <div class="modal-header">
          <h5 id="exampleModalLabel" class="modal-title">Import Product</h5>
          <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
        </div>
        <div class="modal-body">
          <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
           <p>{{__('db.The correct column order is')}} (image, name*, code*, type*, brand, category*, unit_code*, cost*, profit_margin(%), price, product_details, variant_name, item_code, additional_price) {{__('db.and you must follow this')}}.</p>
           <p>If you provide profit_margin, then price will be calculated based on profit_margin: <strong>price = cost * (1 + profit_margin / 100)</strong></p>
           <p>{{__('db.To display Image it must be stored in')}} images/product {{__('db.directory')}}. {{__('db.Image name must be same as product name')}}</p>
           <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{__('db.Upload CSV File')}} *</label>
                        {{Form::file('file', array('class' => 'form-control','required'))}}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label> {{__('db.Sample File')}}</label>
                        <a href="sample_file/sample_products.csv" class="btn btn-info btn-block btn-md"><i class="dripicons-download"></i>  {{__('db.Download')}}</a>
                    </div>
                </div>
           </div>
            {{Form::submit(__('db.submit'), ['class' => 'btn btn-primary'])}}
        </div>
        {!! Form::close() !!}
      </div>
    </div>
</div>

<div id="product-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 id="exampleModalLabel" class="modal-title">{{__('db.Product Details')}}</h5>
          <button id="print-btn" type="button" class="btn btn-default btn-sm ml-3"><i class="dripicons-print"></i> {{__('db.Print')}}</button>
          <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-5" id="slider-content"></div>
                <div class="col-md-5 offset-1" id="product-content"></div>
                @if($role_id <= 2)
                <div class="col-md-12 mt-2" id="product-warehouse-section">
                    <h5>{{__('db.Warehouse Quantity')}}</h5>
                    <table class="table table-bordered table-hover product-warehouse-list">
                        <thead>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                @endif
                <div class="col-md-7 mt-2" id="product-variant-section">
                    <h5>{{__('db.Product Variant Information')}}</h5>
                    <table class="table table-bordered table-hover product-variant-list">
                        <thead>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                @if($role_id <= 2)
                <div class="col-md-5 mt-2" id="product-variant-warehouse-section">
                    <h5>{{__('db.Warehouse quantity of product variants')}}</h5>
                    <table class="table table-bordered table-hover product-variant-warehouse-list">
                        <thead>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

            <h5 id="combo-header"></h5>
            <table class="table table-bordered table-hover item-list">
                <thead>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
      </div>
    </div>
</div>

@endsection
@push('scripts')
<script>

    $("ul#product").siblings('a').attr('aria-expanded','true');
    $("ul#product").addClass("show");
    $("ul#product #product-list-menu").addClass("active");

    @if(config('database.connections.saleprosaas_landlord'))
        if(localStorage.getItem("message")) {
            alert(localStorage.getItem("message"));
            localStorage.removeItem("message");
        }

        numberOfProduct = <?php echo json_encode($numberOfProduct)?>;
        $.ajax({
            type: 'GET',
            async: false,
            url: '{{route("package.fetchData", $general_setting->package_id)}}',
            success: function(data) {
                if(data['number_of_product'] > 0 && data['number_of_product'] <= numberOfProduct) {
                    $("a.add-product-btn").addClass('d-none');
                }
            }
        });
    @endif

    function confirmDelete() {
        if (confirm(@json(__('db.Are you sure want to delete?')))) {
            return true;
        }
        return false;
    }

    var columns = [{"data": "key"},{"data": "name"},{"data": "code"},{"data": "brand"},{"data": "category"},{"data": "qty"},{"data": "unit"},{"data": "price"},{"data": "cost"},{"data": "stock_worth"}];
    var field_name = <?php echo json_encode($field_name) ?>;
    for(i = 0; i < field_name.length; i++) {
        columns.push({"data": field_name[i]});
    }
    columns.push({"data": "options"});

    var warehouse = [];
    var variant = [];
    var qty = [];
    var htmltext;
    var slidertext;
    var product_id = [];
    var all_permission = <?php echo json_encode($all_permission) ?>;
    var role_id = <?php echo json_encode($role_id) ?>;
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;
    var logoUrl = <?php echo json_encode(url('logo', $general_setting->site_logo)) ?>;
    var warehouse_id = <?php echo json_encode($warehouse_id); ?>;
    var product_type = <?php echo json_encode($product_type); ?>;
    var brand_id = <?php echo json_encode($brand_id); ?>;
    var category_id = <?php echo json_encode($category_id); ?>;
    var unit_id = <?php echo json_encode($unit_id); ?>;
    var tax_id = <?php echo json_encode($tax_id); ?>;
    var imeiorvariant = <?php echo json_encode($imeiorvariant); ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $("#warehouse_id").val(warehouse_id);
    $("#product_type").val(product_type);
    $("#brand_id").val(brand_id);
    $("#category_id").val(category_id);
    $("#unit_id").val(unit_id);
    $("#tax_id").val(tax_id);
    $("#imeiorvariant").val(imeiorvariant);

    $( "#select_all" ).on( "change", function() {
        if ($(this).is(':checked')) {
            $("tbody input[type='checkbox']").prop('checked', true);
        }
        else {
            $("tbody input[type='checkbox']").prop('checked', false);
        }
    });

    $(document).on("click", "tr.product-link td:not(:first-child, :last-child)", function() {
        productDetails( $(this).parent().data('product'), $(this).parent().data('imagedata') );
    });

    $(document).on("click", ".view", function(){
        var product = $(this).parent().parent().parent().parent().parent().data('product');
        var imagedata = $(this).parent().parent().parent().parent().parent().data('imagedata');
        // console.log(product);
        productDetails(product, imagedata);
    });

    $("#print-btn").on("click", function() {
          var divToPrint=document.getElementById('product-details');
          var newWin=window.open('','Print-Window');
          newWin.document.open();
          newWin.document.write('<link rel="stylesheet" href="<?php echo asset('vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css"><style type="text/css">@media print {.modal-dialog { max-width: 1000px;} }</style><body onload="window.print()">'+divToPrint.innerHTML+'</body>');
          newWin.document.close();
          setTimeout(function(){newWin.close();},10);
    });

    function productDetails(product, imagedata) {
        product[11] = product[11].replace(/@/g, '"');
        htmltext = slidertext = '';

        htmltext = '<p>{{__("db.Type")}}: '+product[0]+
            '</p><p>{{__("db.name")}}: '+product[1]+
            '</p><p>{{__("db.Code")}}: '+product[2]+
            '</p><p>{{__("db.Brand")}}: '+product[3]+
            '</p><p>{{__("db.category")}}: '+product[4]+
            '</p><p>{{__("db.Quantity")}}: '+product[17]+
            '</p><p>{{__("db.Unit")}}: '+product[5]+
            (role_id < 3 ? '</p><p>{{__("db.Cost")}}: '+product[6] : '') +
            '</p><p>{{__("db.Price")}}: '+product[7]+
            '</p><p>{{__("db.Tax")}}: '+product[8]+
            '</p><p>{{__("db.Tax Method")}} : '+product[9]+
            '</p><p>{{__("db.Alert Quantity")}} : '+product[10]+
            '</p><p>{{__("db.Product Details")}}: </p>'+product[11];

        if(product[18]) {
            var product_image = product[18].split(",");
            if(product_image.length > 1) {
                slidertext = '<div id="product-img-slider" class="carousel slide" data-ride="carousel"><div class="carousel-inner">';
                for (var i = 0; i < product_image.length; i++) {
                    if(!i)
                        slidertext += '<div class="carousel-item active"><img src="images/product/'+product_image[i]+'" height="300" width="100%"></div>';
                    else
                        slidertext += '<div class="carousel-item"><img src="images/product/'+product_image[i]+'" height="300" width="100%"></div>';
                }
                slidertext += '</div><a class="carousel-control-prev" href="#product-img-slider" data-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="sr-only">Previous</span></a><a class="carousel-control-next" href="#product-img-slider" data-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="sr-only">Next</span></a></div>';
            }
            else {
                slidertext = '<img src="images/product/'+product[18]+'" height="300" width="100%">';
            }
        }
        else {
            slidertext = '<img src="images/product/zummXD2dvAtI.png" height="300" width="100%">';
        }
        $("#combo-header").text('');
        $("table.item-list thead").remove();
        $("table.item-list tbody").remove();
        $("table.product-warehouse-list thead").remove();
        $("table.product-warehouse-list tbody").remove();
        $(".product-variant-list thead").remove();
        $(".product-variant-list tbody").remove();
        $(".product-variant-warehouse-list thead").remove();
        $(".product-variant-warehouse-list tbody").remove();
        $("#product-warehouse-section").addClass('d-none');
        $("#product-variant-section").addClass('d-none');
        $("#product-variant-warehouse-section").addClass('d-none');
        if(product[0] == 'combo') {
            $("#combo-header").text('{{__("db.Combo Products")}}');
            // console.log(product)
            product_list = product[13].split(",");
            variant_list = product[14].split(",");
            qty_list = product[15].split(",");
            price_list = product[16].split(",");
            combo_unit = product[20].split(",");
            wastage_percent = product[21].split(",");
            $(".item-list thead").remove();
            $(".item-list tbody").remove();
            var newHead = $("<thead>");
            var newBody = $("<tbody>");
            var newRow = $("<tr>");
            newRow.append('<th>{{__("db.product")}}</th><th>{{__("db.Wastage Percent")}}</th><th>{{__("db.Quantity")}}</th><th>{{__("db.Price")}}</th>');
            newHead.append(newRow);
                // console.log(product)
            $(product_list).each(function(i) {
                if(!variant_list[i])
                    variant_list[i] = 0;
                $.get('products/getdata/' + product_list[i] + '/' + variant_list[i], function(data) {
                    var newRow = $("<tr>");
                    var cols = '';
                    cols += '<td>' + data['name'] +' [' + data['code'] + ']</td>';
                    cols += '<td>' + wastage_percent[i] +'</td>';
                    cols += '<td>' + qty_list[i] + '('+combo_unit[i]+')</td>';
                    cols += '<td>' + price_list[i] + '</td>';

                    newRow.append(cols);
                    newBody.append(newRow);
                });
            });

            $("table.item-list").append(newHead);
            $("table.item-list").append(newBody);
        }
        if(product[0] == 'standard' || product[0] == 'combo') {
            if(product[19]) {
                $.get('products/variant-data/' + product[12], function(variantData) {
                    var newHead = $("<thead>");
                    var newBody = $("<tbody>");
                    var newRow = $("<tr>");
                    newRow.append('<th>{{__("db.Variant")}}</th><th>{{__("db.Item Code")}}</th><th>{{__("db.Additional Cost")}}</th><th>{{__("db.Additional Price")}}</th><th>{{__("db.Qty")}}</th>');
                    newHead.append(newRow);
                    $.each(variantData, function(i) {
                        var newRow = $("<tr>");
                        var cols = '';
                        cols += '<td>' + variantData[i]['name'] + '</td>';
                        cols += '<td>' + variantData[i]['item_code'] + '</td>';
                        if(variantData[i]['additional_cost'])
                            cols += '<td>' + variantData[i]['additional_cost'] + '</td>';
                        else
                            cols += '<td>0</td>';
                        if(variantData[i]['additional_price'])
                            cols += '<td>' + variantData[i]['additional_price'] + '</td>';
                        else
                            cols += '<td>0</td>';
                        cols += '<td>' + variantData[i]['qty'] + '</td>';

                        newRow.append(cols);
                        newBody.append(newRow);
                    });
                    $("table.product-variant-list").append(newHead);
                    $("table.product-variant-list").append(newBody);
                });
                $("#product-variant-section").removeClass('d-none');
            }
            if(role_id <= 2) {
                $.get('products/product_warehouse/' + product[12], function(data) {
                    // console.log(data);
                    if(data.product_warehouse[0].length != 0) {
                        warehouse = data.product_warehouse[0];
                        qty = data.product_warehouse[1];
                        batch = data.product_warehouse[2];
                        expired_date = data.product_warehouse[3];
                        imei_numbers = data.product_warehouse[4];
                        // console.log(imei_numbers, 'hi imei');
                        var newHead = $("<thead>");
                        var newBody = $("<tbody>");
                        var newRow = $("<tr>");
                        var productQty = 0;
                        newRow.append('<th>{{__("db.Warehouse")}}</th><th>{{__("db.Batch No")}}</th><th>{{__("db.Expired Date")}}</th><th>{{__("db.Quantity")}}</th><th>{{__("db.IMEI or Serial Numbers")}}</th>');
                        newHead.append(newRow);
                        $.each(warehouse, function(index) {
                            // productQty += qty[index];
                            var newRow = $("<tr>");
                            var cols = '';
                            cols += '<td>' + warehouse[index] + '</td>';
                            cols += '<td>' + batch[index] + '</td>';
                            cols += '<td>' + expired_date[index] + '</td>';
                            cols += '<td>' + qty[index] + '</td>';
                            // console.log(imei_numbers);
                            if (imei_numbers.length <= index) {
                                cols += '<td style="max-height: 100px; overflow-y: auto; word-break: break-word; white-space: normal; display: block; padding-right: 10px;">' + 'N/A' + '</td>';
                            } else {
                                cols += '<td style="max-height: 100px; overflow-y: auto; word-break: break-word; white-space: normal; display: block; padding-right: 10px;">' + imei_numbers[index].split(',').join(",<br/>") + '</td>';
                            }

                            newRow.append(cols);
                            newBody.append(newRow);
                            $("table.product-warehouse-list").append(newHead);
                            $("table.product-warehouse-list").append(newBody);
                        });
                        // console.log(productQty);
                        $("#product-warehouse-section").removeClass('d-none');
                    }
                    if(data.product_variant_warehouse[0].length != 0) {
                        warehouse = data.product_variant_warehouse[0];
                        variant = data.product_variant_warehouse[1];
                        qty = data.product_variant_warehouse[2];
                        var newHead = $("<thead>");
                        var newBody = $("<tbody>");
                        var newRow = $("<tr>");
                        newRow.append('<th>{{__("db.Warehouse")}}</th><th>{{__("db.Variant")}}</th><th>{{__("db.Quantity")}}</th>');
                        newHead.append(newRow);
                        $.each(warehouse, function(index){
                            var newRow = $("<tr>");
                            var cols = '';
                            cols += '<td>' + warehouse[index] + '</td>';
                            cols += '<td>' + variant[index] + '</td>';
                            cols += '<td>' + qty[index] + '</td>';

                            newRow.append(cols);
                            newBody.append(newRow);
                            $("table.product-variant-warehouse-list").append(newHead);
                            $("table.product-variant-warehouse-list").append(newBody);
                        });
                        $("#product-variant-warehouse-section").removeClass('d-none');
                    }
                });
            }
        }

        $('#product-content').html(htmltext);
        $('#slider-content').html(slidertext);
        $('#product-details').modal('show');
        $('#product-img-slider').carousel(0);
    }

    $('#toggle-filter').on('click', function() {
        $('#filter-card').slideToggle('slow');
    });

    $(document).ready(function() {
        var table = $('#product-data-table').DataTable( {
            responsive: true,
            fixedHeader: {
                header: true,
                footer: true
            },
            "processing": true,
            "serverSide": true,
            "ajax":{
                url:"products/product-data",
                data:{
                    all_permission: all_permission,
                    warehouse_id: warehouse_id,
                    product_type: product_type,
                    brand_id: brand_id,
                    category_id: category_id,
                    unit_id: unit_id,
                    tax_id: tax_id,
                    imeiorvariant: imeiorvariant,
                },
                dataType: "json",
                type:"post"
            },
            "createdRow": function( row, data, dataIndex ) {
                $(row).addClass('product-link');
                $(row).attr('data-product', data['product']);
                $(row).attr('data-imagedata', data['imagedata']);
            },
            "columns": columns,
            'language': {
                /*'searchPlaceholder': "{{__('db.Type Product Name or Code')}}",*/
                'lengthMenu': '_MENU_ {{__("db.records per page")}}',
                 "info":      '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
                "search":  '{{__("db.Search")}}',
                'paginate': {
                        'previous': '<i class="dripicons-chevron-left"></i>',
                        'next': '<i class="dripicons-chevron-right"></i>'
                }
            },
            order:[['1', 'asc']],
            'columnDefs': [
                {
                    "orderable": false,
                    'targets': [0, 9, 10]
                },
                {
                    'render': function(data, type, row, meta){
                        if(type === 'display'){
                            data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                        }

                       return data;
                    },
                    'checkboxes': {
                       'selectRow': true,
                       'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                    },
                    'targets': [0]
                }
            ],
            'select': { style: 'multi', selector: 'td:first-child'},
            'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "{{ __('db.All') }}"]],
            dom: '<"row"lfB>rtip',
            buttons: [
                {
                    extend: 'pdf',
                    text: '<i title="{{ __('db.export to pdf') }}" class="fa fa-file-pdf-o"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible',
                        stripHtml: false
                    },
                    customize: function(doc) {
                        for (var i = 1; i < doc.content[1].table.body.length; i++) {
                            if (doc.content[1].table.body[i][0].text.indexOf('<img src=') !== -1) {
                                var imagehtml = doc.content[1].table.body[i][0].text;
                                var regex = /<img.*?src=['"](.*?)['"]/;
                                var src = regex.exec(imagehtml)[1];
                                var tempImage = new Image();
                                tempImage.src = src;
                                var canvas = document.createElement("canvas");
                                canvas.width = tempImage.width;
                                canvas.height = tempImage.height;
                                var ctx = canvas.getContext("2d");
                                ctx.drawImage(tempImage, 0, 0);
                                var imagedata = canvas.toDataURL("image/png");
                                delete doc.content[1].table.body[i][0].text;
                                doc.content[1].table.body[i][0].image = imagedata;
                                doc.content[1].table.body[i][0].fit = [30, 30];
                            }
                        }
                    },
                },
                {
                    extend: 'excel',
                    text: '<i title="{{ __('db.export to excel') }}" class="dripicons-document-new"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible',
                        format: {
                            body: function ( data, row, column, node ) {
                                if (column === 0 && (data.indexOf('<img src=') !== -1)) {
                                    var regex = /<img.*?src=['"](.*?)['"]/;
                                    data = regex.exec(data)[1];
                                }
                                return data;
                            }
                        }
                    }
                },
                {
                    extend: 'csv',
                    text: '<i title="{{ __('db.export to csv') }}" class="fa fa-file-text-o"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible',
                        format: {
                            body: function ( data, row, column, node ) {
                                if (column === 0 && (data.indexOf('<img src=') !== -1)) {
                                    var regex = /<img.*?src=['"](.*?)['"]/;
                                    data = regex.exec(data)[1];
                                }
                                return data;
                            }
                        }
                    }
                },
                {
                    extend: 'print',
                    title: '',
                    text: '<i title="{{ __('db.print') }}" class="fa fa-print"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible',
                        stripHtml: false
                    },
                    repeatingHead: {
                        logo: logoUrl,
                        logoPosition: 'left',
                        logoStyle: '',
                        title: '<h3>Product List</h3>'
                    }
                    /*customize: function ( win ) {
                        $(win.document.body)
                            .prepend(
                                '<img src="http://datatables.net/media/images/logo-fade.png" style="margin:10px;" />'
                            );
                    }*/
                },
                {
                    text: '<i title="{{ __('db.delete') }}" class="dripicons-cross"></i>',
                    className: 'buttons-delete',
                    action: function ( e, dt, node, config ) {
                        if(user_verified == '1') {
                            product_id.length = 0;
                            $(':checkbox:checked').each(function(i){
                                if(i){
                                    var product_data = $(this).closest('tr').data('product');
                                    if(product_data)
                                        product_id[i-1] = product_data[12];
                                }
                            });
                            if(product_id.length && confirmDelete()) {
                                $.ajax({
                                    type:'POST',
                                    url:'products/deletebyselection',
                                    data:{
                                        productIdArray: product_id
                                    },
                                    success:function(data) {
                                        alert(data);
                                        //dt.rows({ page: 'current', selected: true }).deselect();
                                        dt.rows({ page: 'current', selected: true }).remove().draw(false);
                                    }
                                });
                            }
                            else if(!product_id.length)
                                alert(@json(__('db.No product is selected!')));
                        }
                        else
                            alert(@json(__('db.This feature is disable for demo!')));
                    }
                },
                {
                    extend: 'colvis',
                    text: '<i title="{{ __('db.column visibility') }}" class="fa fa-eye"></i>',
                    columns: ':gt(0)'
                },
            ],
        } );

        let hasFilters = window.location.search.length > 0;
        if (hasFilters) {
            $('#filter-card').show();
        }
    } );

    if(all_permission.indexOf("products-delete") == -1)
        $('.buttons-delete').addClass('d-none');

    $('select').selectpicker();

</script>
@endpush
