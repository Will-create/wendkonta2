@extends('backend.layout.main') @section('content')

<section class="forms">
    <div class="container-fluid">
	    <h4 class="text-center">{{__('db.Product Expiry Report')}}</h4>
    </div>
    <div class="table-responsive mb-4">
        <table id="report-table" class="table table-hover">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{__('db.Image')}}</th>
                    <th>{{__('db.Product Name')}}</th>
                    <th>{{__('db.Product Code')}}</th>
                    <th>{{__('db.Batch No')}}</th>
                    <th>{{__('db.Expiry Date')}}</th>
                    <th>{{__('db.Quantity')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_product_data as $key=>$product)
                <tr>
                    <td>{{$key}}</td>
                    <td>
                    <?php
                        $images = explode(",", $product->image);
                        $product->base_image = $images[0];
                    ?>
                        <img src="{{url('images/product',$product->base_image)}}" height="80" width="80">
                    </td>
                    <td>{{$product->name}}</td>
                    <td>{{$product->code}}</td>
                    <td>{{$product->batch_no}}</td>
                    @if(date('Y-m-d') >= $product->expired_date)
                    <td>
                        <div class="badge badge-danger">{{date($general_setting->date_format, strtotime($product->expired_date))}}</div>
                    </td>
                    @else
                    <td>
                        <div class="badge badge-warning">{{date($general_setting->date_format, strtotime($product->expired_date))}}</div>
                    </td>
                    @endif
                    <td>{{number_format((float)($product->qty), $general_setting->decimal, '.', '')}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #qtyAlert-report-menu").addClass("active");

    $('#report-table').DataTable( {
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{__("db.records per page")}}',
             "info":      '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search":  '{{__("db.Search")}}',
            'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false,
                'targets': 0
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
            },
            {
                extend: 'excel',
                text: '<i title="export to excel" class="dripicons-document-new"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            }
        ],
    } );

</script>
@endpush
