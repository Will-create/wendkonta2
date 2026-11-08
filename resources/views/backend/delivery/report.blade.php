@extends('backend.layout.main')
@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header mt-2">
                <h3 class="text-center">{{__('db.Delivery Report')}}</h3>
            </div>
            <form method="get" action="{{ route('delivery.report') }}" id="delivery-report-filter">
                <div class="row mb-3">
                    <div class="col-md-2 mt-3">
                        <label>{{__('db.Year')}}</label>
                        <select name="year" id="delivery-report-year" class="form-control">
                            <option value="all" {{ $year === 'all' ? 'selected' : '' }}>{{__('db.All years')}}</option>
                            @foreach($years as $reportYear)
                                <option value="{{$reportYear}}" {{ (string) $year === (string) $reportYear ? 'selected' : '' }}>{{$reportYear}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mt-3">
                        <label>{{__('db.Month')}}</label>
                        <select name="month" id="delivery-report-month" class="form-control">
                            <option value="all" {{ $month === 'all' ? 'selected' : '' }}>{{__('db.All months')}}</option>
                            @foreach($months as $reportMonth => $reportMonthName)
                                <option value="{{$reportMonth}}" {{ (string) $month === (string) $reportMonth ? 'selected' : '' }}>{{$reportMonthName}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mt-3">
                        <label>{{__('db.date')}}</label>
                        <input type="date" name="start_date" id="delivery-report-start" class="form-control" value="{{$start_date}}">
                    </div>
                    <div class="col-md-2 mt-3">
                        <label>{{__('db.To')}}</label>
                        <input type="date" name="end_date" id="delivery-report-end" class="form-control" value="{{$end_date}}">
                    </div>
                    <div class="col-md-2 mt-3">
                        <label>{{__('db.Warehouse')}}</label>
                        <select name="warehouse_id" class="selectpicker form-control" data-live-search="true">
                            <option value="0">{{__('db.All Warehouse')}}</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{$warehouse->id}}" {{ (int) $warehouse_id === (int) $warehouse->id ? 'selected' : '' }}>{{$warehouse->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mt-3">
                        <label>{{__('db.Progress')}}</label>
                        <select name="progress" class="form-control">
                            <option value="all" {{ $progress === 'all' ? 'selected' : '' }}>{{__('db.All')}}</option>
                            <option value="not_delivered" {{ $progress === 'not_delivered' ? 'selected' : '' }}>{{__('db.Not yet delivered')}}</option>
                            <option value="in_progress" {{ $progress === 'in_progress' ? 'selected' : '' }}>{{__('db.In progress')}}</option>
                            <option value="partial" {{ $progress === 'partial' ? 'selected' : '' }}>{{__('db.Partially delivered')}}</option>
                            <option value="full" {{ $progress === 'full' ? 'selected' : '' }}>{{__('db.Fully delivered')}}</option>
                        </select>
                    </div>
                    <div class="col-md-12 mt-3">
                        <button class="btn btn-primary" type="submit">{{__('db.submit')}}</button>
                        <button class="btn btn-default" type="submit" id="delivery-report-all">{{__('db.Show complete report')}}</button>
                        <p class="text-muted mt-2"><small>{{__('db.Delivery report filter help')}}</small></p>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-3"><div class="wrapper count-title"><div class="name"><strong>{{__('db.Sales in period')}}</strong></div><div class="count-number">{{$summary['sales']}}</div></div></div>
            <div class="col-sm-3"><div class="wrapper count-title"><div class="name"><strong>{{__('db.Not yet delivered')}}</strong></div><div class="count-number">{{$summary['not_delivered']}}</div></div></div>
            <div class="col-sm-3"><div class="wrapper count-title"><div class="name"><strong>{{__('db.In progress')}}</strong></div><div class="count-number">{{$summary['in_progress']}}</div></div></div>
            <div class="col-sm-3"><div class="wrapper count-title"><div class="name"><strong>{{__('db.Partially delivered')}}</strong></div><div class="count-number">{{$summary['partial']}}</div></div></div>
            <div class="col-sm-3 mt-3"><div class="wrapper count-title"><div class="name"><strong>{{__('db.Fully delivered')}}</strong></div><div class="count-number">{{$summary['full']}}</div></div></div>
            <div class="col-sm-3 mt-3"><div class="wrapper count-title"><div class="name"><strong>{{__('db.Qty sold')}}</strong></div><div class="count-number">{{\App\Services\DeliveryProgress::formatQty($summary['sold'])}}</div></div></div>
            <div class="col-sm-3 mt-3"><div class="wrapper count-title"><div class="name"><strong>{{__('db.Qty delivered')}}</strong></div><div class="count-number">{{\App\Services\DeliveryProgress::formatQty($summary['delivered'])}}</div></div></div>
            <div class="col-sm-3 mt-3"><div class="wrapper count-title"><div class="name"><strong>{{__('db.Qty remaining')}}</strong></div><div class="count-number">{{\App\Services\DeliveryProgress::formatQty($summary['remaining'])}}</div></div></div>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header"><h4>{{__('db.Sales and delivery progress')}}</h4></div>
            <div class="card-body table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.reference')}}</th>
                            <th>{{__('db.customer')}}</th>
                            <th>{{__('db.Progress')}}</th>
                            <th>{{__('db.Sold')}}</th>
                            <th>{{__('db.Delivered')}}</th>
                            <th>{{__('db.Remaining')}}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($saleRows as $row)
                        <tr>
                            <td>{{$row['date']}}</td>
                            <td>{{$row['reference_no']}}</td>
                            <td>{{$row['customer']}}</td>
                            <td>{{$row['label']}}</td>
                            <td>{{\App\Services\DeliveryProgress::formatQty($row['sold'])}}</td>
                            <td>{{\App\Services\DeliveryProgress::formatQty($row['delivered'])}}</td>
                            <td>{{\App\Services\DeliveryProgress::formatQty($row['remaining'])}}</td>
                            <td><a href="{{$row['invoice']}}">{{__('db.Generate Invoice')}}</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="8">{{__('db.No sales match these filters.')}}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4>{{__('db.Products awaiting delivery')}}</h4></div>
            <div class="card-body table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.Sale')}}</th>
                            <th>{{__('db.customer')}}</th>
                            <th>{{__('db.product')}}</th>
                            <th>{{__('db.Code')}}</th>
                            <th>{{__('db.Sold')}}</th>
                            <th>{{__('db.Delivered')}}</th>
                            <th>{{__('db.Remaining')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($awaiting as $item)
                        <tr>
                            <td>{{$item['date']}}</td>
                            <td>{{$item['sale']}}</td>
                            <td>{{$item['customer']}}</td>
                            <td>{{$item['product']}}</td>
                            <td>{{$item['code']}}</td>
                            <td>{{\App\Services\DeliveryProgress::formatQty($item['sold'])}}</td>
                            <td>{{\App\Services\DeliveryProgress::formatQty($item['delivered'])}}</td>
                            <td>{{\App\Services\DeliveryProgress::formatQty($item['remaining'])}}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8">{{__('db.No product is waiting for delivery in this selection.')}}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4>{{__('db.Deliveries in the period')}}</h4></div>
            <div class="card-body table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.Delivery Reference')}}</th>
                            <th>{{__('db.Sale Reference')}}</th>
                            <th>{{__('db.customer')}}</th>
                            <th>{{__('db.status')}}</th>
                            <th>{{__('db.product')}}</th>
                            <th>{{__('db.Note')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveries as $delivery)
                        <tr>
                            <td>{{ date(config('date_format'), strtotime($delivery->created_at)) }}</td>
                            <td>{{$delivery->reference_no}}</td>
                            <td>{{$delivery->sale_reference}}</td>
                            <td>{{$delivery->customer_name}}</td>
                            <td>{{ \App\Services\DeliveryProgress::headerStatusLabel((string) $delivery->status) }}</td>
                            <td>{{ isset($linesByDelivery[$delivery->id]) ? implode(', ', $linesByDelivery[$delivery->id]) : '' }}</td>
                            <td>{{$delivery->note}}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7">{{__('db.No delivery was recorded in this period.')}}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#sale").siblings('a').attr('aria-expanded','true');
    $("ul#sale").addClass("show");
    $("ul#sale #delivery-report-menu").addClass("active");
    $('.selectpicker').selectpicker('refresh');
    function deliveryReportPeriodIsOpen() {
        return $('#delivery-report-year').val() === 'all' && $('#delivery-report-month').val() === 'all';
    }
    $('#delivery-report-year, #delivery-report-month').on('change', function() {
        if (deliveryReportPeriodIsOpen()) {
            $('#delivery-report-start, #delivery-report-end').val('');
        }
    });
    $('#delivery-report-all').on('click', function() {
        $('#delivery-report-year, #delivery-report-month').val('all');
        $('#delivery-report-start, #delivery-report-end').val('');
    });
</script>
@endpush
