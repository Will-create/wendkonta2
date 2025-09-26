@extends('backend.layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header mt-2">
                <h3 class="text-center">{{__('db.Production List')}}</h3>
            </div>
            {{-- {!! Form::open(['route' => 'productions.index', 'method' => 'get']) !!} --}}
            <div class="row ml-1 mt-2">
                <div class="col-md-3">
                    <div class="form-group">
                        <label><strong>{{__('db.date')}}</strong></label>
                        <input type="text" class="daterangepicker-field form-control" value="{{$starting_date}} To {{$ending_date}}" required />
                        <input type="hidden" name="starting_date" value="{{$starting_date}}" />
                        <input type="hidden" name="ending_date" value="{{$ending_date}}" />
                    </div>
                </div>
                <div class="col-md-3 @if(\Auth::user()->role_id > 2){{'d-none'}}@endif">
                    <div class="form-group">
                        <label><strong>{{__('db.Warehouse')}}</strong></label>
                        <select id="warehouse_id" name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" >
                            <option value="0">{{__('db.All Warehouse')}}</option>
                            @foreach($lims_warehouse_list as $warehouse)
                                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label><strong>{{__('db.status')}}</strong></label>
                        <select id="status" class="form-control" name="production_status">
                            <option value="0">{{__('db.All')}}</option>
                            <option value="1">{{__('db.Completed')}}</option>
                        </select>
                    </div>
                </div>
                {{-- <div class="col-md-2 mt-3">
                    <div class="form-group">
                        <button class="btn btn-primary" id="filter-btn" type="submit">{{__('db.submit')}}</button>
                    </div>
                </div> --}}
            </div>
            {{-- {!! Form::close() !!} --}}
        </div>
        <a href="{{route('productions.create')}}" class="btn btn-info"><i class="dripicons-plus"></i> {{__('db.Add Production')}}</a>&nbsp;
    </div>
    <div class="table-responsive">
        <table id="production-table" class="table production-list" style="width: 100%">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{__('db.date')}}</th>
                    <th>{{__('db.reference')}}</th>
                    <th>{{__('db.status')}}</th>
                    <th>{{__('db.product')}}</th>
                    <th>{{__('db.Warehouse')}}</th>
                    <th>{{__('db.Quantity')}}</th>
                    <th>{{__('db.grand total')}}</th>
                    <th class="not-exported">{{__('db.action')}}</th>
                </tr>
            </thead>

            <tfoot class="tfoot active">
                <th></th>
                <th>{{__('db.Total')}}</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tfoot>
        </table>
    </div>
</section>

<div id="production-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
      <div class="modal-content">
        <div class="container mt-3 pb-2 border-bottom">
            <div class="row">
                <div class="col-md-6 d-print-none">
                    <button id="print-btn" type="button" class="btn btn-default btn-sm"><i class="dripicons-print"></i> {{__('db.Print')}}</button>
                </div>
                <div class="col-md-6 d-print-none">
                    <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="col-md-12">
                    <h3 id="exampleModalLabel" class="modal-title text-center container-fluid">{{$general_setting->site_title}}</h3>
                </div>
                <div class="col-md-12 text-center">
                    <i style="font-size: 15px;">{{__('db.Production Details')}}</i>
                </div>
            </div>
        </div>
            <div id="production-content" class="modal-body"></div>
            <br>
            <table class="table table-bordered product-production-list">
                <thead>
                    <th>#</th>
                    <th>{{__('db.product')}}</th>
                    <th>{{__('db.Qty') }}</th>
                    <th>{{__('db.Wastage Percent')}}</th>
                    <th>{{__('db.Price')}}</th>
                    <th>{{__('db.Subtotal')}}</th>
                </thead>
                <tbody>
                </tbody>
            </table>
            <div id="production-footer" class="modal-body"></div>
      </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">

    $(".daterangepicker-field").daterangepicker({
      callback: function(startDate, endDate, period){
        var starting_date = startDate.format('YYYY-MM-DD');
        var ending_date = endDate.format('YYYY-MM-DD');
        var title = starting_date + ' To ' + ending_date;
        $(this).val(title);
        $('input[name="starting_date"]').val(starting_date);
        $('input[name="ending_date"]').val(ending_date);
        reloadTable();
      }
    });

    $("ul#manufacturing").siblings('a').attr('aria-expanded','true');
    $("ul#manufacturing").addClass("show");
    $("ul#manufacturing #production-list-menu").addClass("active");


    var production_id = [];
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;
    var starting_date = <?php echo json_encode($starting_date); ?>;
    var ending_date = <?php echo json_encode($ending_date); ?>;
    var warehouse_id = <?php echo json_encode($warehouse_id); ?>;
    var status = <?php echo json_encode($status); ?>;


    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $("#warehouse_id").val(warehouse_id);
    $("#status").val(status);

    $('.selectpicker').selectpicker('refresh');

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    $(document).on("click", "tr.production-link td:not(:first-child, :last-child)", function(){
        var production = $(this).parent().data('production');
        productionDetails(production);
    });

    $(document).on("click", ".view", function(){
        var production = $(this).parent().parent().parent().parent().parent().data('production');
        productionDetails(production);
    });

    $("#print-btn").on("click", function(){
        var divContents = document.getElementById("production-details").innerHTML;
        var a = window.open('');
        a.document.write('<html>');
        a.document.write('<body><style>body{font-family: sans-serif;line-height: 1.15;-webkit-text-size-adjust: 100%;}.d-print-none{display:none}.text-center{text-align:center}.row{width:100%;margin-right: -15px;margin-left: -15px;}.col-md-12{width:100%;display:block;padding: 5px 15px;}.col-md-6{width: 50%;float:left;padding: 5px 15px;}table{width:100%;margin-top:30px;}th{text-aligh:left;}td{padding:10px}table, th, td{border: 1px solid black; border-collapse: collapse;}</style><style>@media print {.modal-dialog { max-width: 1000px;} }</style>');
        a.document.write(divContents);
        a.document.write('</body></html>');
        a.document.close();
        setTimeout(function(){a.close();},10);
        a.print();
    });



    // data table real time chage

    // Date Range Change
    $('.daterangepicker-field').on('change', function () {
        alert()
        let dateRange = $(this).val().split(" To ");
        $('input[name="starting_date"]').val(dateRange[0]);
        $('input[name="ending_date"]').val(dateRange[1]);
        reloadTable();
    });

    // Warehouse Change
    $('#warehouse_id').on('change', function () {
        reloadTable();
    });

    // Status Change
    $('#status').on('change', function () {
        reloadTable();
    });

    // Common Reload Function
    function reloadTable() {
        $('#production-table').DataTable().destroy();
        loadProductionTable(
            $('input[name="starting_date"]').val(),
            $('input[name="ending_date"]').val(),
            $('#warehouse_id').val(),
            $('#status').val()
        );
    }


    loadProductionTable(starting_date, ending_date, warehouse_id, status)

    function   loadProductionTable(starting_date, ending_date, warehouse_id, status){
        $('#production-table').DataTable( {
            "processing": true,
            "serverSide": true,
            "ajax":{
                url:"productions/production-data",
                data: {
                    starting_date: starting_date,
                    ending_date: ending_date,
                    warehouse_id: warehouse_id,
                    status: status,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: "json",
                type:"post",
                // success:function(data){
                //     console.log(data);
                // }
            },
            "createdRow": function( row, data, dataIndex ) {
                $(row).addClass('production-link');
                $(row).attr('data-production', data['production']);
            },
            "columns": [
                {"data": "key"},
                {"data": "date"},
                {"data": "reference_no"},
                {"data": "status"},
                {"data": "product"},
                {"data": "warehouse"},
                {"data": "quantity"},
                {"data": "grand_total"},
                {"data": "options"},
            ],
            'language': {
                'lengthMenu': '_MENU_ {{__("db.records per page")}}',
                "info":      '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
                "search":  '{{__("db.Search")}}',
                'paginate': {
                        'previous': '<i class="dripicons-chevron-left"></i>',
                        'next': '<i class="dripicons-chevron-right"></i>'
                }
            },
            order:[['1', 'desc']],
            'columnDefs': [
                {
                    "orderable": false,
                    'targets': [0, 3,8]
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
            'select': { style: 'multi',  selector: 'td:first-child'},
            'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
            dom: '<"row"lfB>rtip',
            buttons: [
                {
                    extend: 'pdf',
                    text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        datatable_sum(dt, true);
                        $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
                        datatable_sum(dt, false);
                    },
                    footer:true
                },
                {
                    extend: 'excel',
                    text: '<i title="export to excel" class="dripicons-document-new"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        datatable_sum(dt, true);
                        $.fn.dataTable.ext.buttons.excelHtml5.action.call(this, e, dt, button, config);
                        datatable_sum(dt, false);
                    },
                    footer:true
                },
                {
                    extend: 'csv',
                    text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        datatable_sum(dt, true);
                        $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                        datatable_sum(dt, false);
                    },
                    footer:true
                },
                {
                    extend: 'print',
                    text: '<i title="print" class="fa fa-print"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        datatable_sum(dt, true);
                        $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                        datatable_sum(dt, false);
                    },
                    footer:true
                },
                {
                    extend: 'colvis',
                    text: '<i title="column visibility" class="fa fa-eye"></i>',
                    columns: ':gt(0)'
                },
            ],
            drawCallback: function () {
                var api = this.api();
                datatable_sum(api, false);
            }
        });
    }



        function datatable_sum(dt_selector, is_calling_first) {
            if (dt_selector.rows( '.selected' ).any() && is_calling_first) {
                var rows = dt_selector.rows( '.selected' ).indexes();
                $( dt_selector.column( 7 ).footer() ).html(dt_selector.cells( rows, 7, { page: 'current' } ).data().sum().toFixed({{$general_setting->decimal}}));
            }
            else {
                $( dt_selector.column( 7 ).footer() ).html(dt_selector.column( 7, {page:'current'} ).data().sum().toFixed({{$general_setting->decimal}}));
            }
        }

        function productionDetails(production){
            var htmltext = '<strong>{{__("db.date")}}: </strong>'+production[0]+'<br><strong>{{__("db.reference")}}: </strong>'+production[1]+'<br><strong>{{__("db.status")}}: </strong>'+production[2]+'<br><strong>{{__("db.Warehouse")}}: </strong>'+production[4];
            if(production[12])
                htmltext += '<strong>{{__("db.Attach Document")}}: </strong><a href="documents/production/'+production[25]+'">Download</a><br>';

            $(".product-production-list tbody").remove();
        $.get('productions/product_production/' + production[3], function(response) {

        var newBody = $("<tbody>");

        if (!response.status || !response.data.length) {
            var newRow = $("<tr>");
            var cols = '<td colspan="8">Something is wrong!</td>';
            newRow.append(cols);
            newBody.append(newRow);
        } else {
            var data = response.data;
            var production = response.production_info;

            $.each(data, function(index, item) {
                var newRow = $("<tr>");
                var cols = '';
                cols += '<td><strong>' + (index + 1) + '</strong></td>';
                cols += '<td>' + item.name + ' [' + item.code + ']</td>';
                cols += '<td>' + item.qty + '[' + item.unit_name+']</td>';
                cols += '<td>' + item.wastage_percent + '</td>';
                cols += '<td>' + item.unit_price + '</td>';
                cols += '<td>' + (item.subtotal).toFixed(2) + '</td>';
                newRow.append(cols);
                newBody.append(newRow);
            });

            // Total row
            var totalSubtotal = data.reduce((acc, curr) => acc + parseFloat(curr.subtotal), 0);
            var newRow = $("<tr>");
            var cols = '';
            cols += '<td colspan="5"><strong>Total:</strong></td>';
            // cols += '<td>' + production[5] + '</td>';
            cols += '<td>' + totalSubtotal.toFixed(2) + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            // Shipping Cost row
            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan="5"><strong>Shipping Cost:</strong></td>';
            cols += '<td>' + production.shipping_cost + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            // prduction Cost row
            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan="5"><strong>Production Cost:</strong></td>';
            cols += '<td>' + production.production_cost + '</td>';
            newRow.append(cols);
            newBody.append(newRow);


            //  Total Qty
            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan="5"><strong>Total Quantity:</strong></td>';
            cols += '<td>' + production.total_qty +'</td>';
            newRow.append(cols);
            newBody.append(newRow);

            // Grand Total row
            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan="5"><strong>Grand Total:</strong></td>';
            cols += '<td>' + production.grand_total+ '</td>';
            newRow.append(cols);
            newBody.append(newRow);
        }

        // Clear and append to table
        $("table.product-production-list tbody").remove(); // Clear previous
        $("table.product-production-list").append(newBody);
    });


        var htmlfooter = '<p><strong>{{__("db.Note")}}:</strong> '+production[9]+'</p><strong>{{__("db.Created By")}}:</strong><br>'+production[10]+'<br>'+production[11];

        $('#production-content').html(htmltext);
        $('#production-footer').html(htmlfooter);
        $('#production-details').modal('show');
    }


</script>
@endpush
