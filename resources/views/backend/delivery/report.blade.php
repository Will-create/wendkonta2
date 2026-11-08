@extends('backend.layout.main')
@section('content')

<section class="forms">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header mt-2">
                <h3 class="text-center">{{__('Delivery Report')}}</h3>
            </div>
            <form method="get" action="{{ route('delivery.report') }}">
                <div class="row mb-3">
                    <div class="col-md-3 mt-3">
                        <label>{{__('db.date')}}</label>
                        <input type="date" name="start_date" class="form-control" value="{{$start_date}}" required>
                    </div>
                    <div class="col-md-3 mt-3">
                        <label>{{__('To')}}</label>
                        <input type="date" name="end_date" class="form-control" value="{{$end_date}}" required>
                    </div>
                    <div class="col-md-3 mt-3">
                        <label>{{__('db.Warehouse')}}</label>
                        <select name="warehouse_id" class="selectpicker form-control" data-live-search="true">
                            <option value="0">{{__('db.All Warehouse')}}</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{$warehouse->id}}" {{ (int) $warehouse_id === (int) $warehouse->id ? 'selected' : '' }}>{{$warehouse->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mt-3">
                        <label>{{__('Progress')}}</label>
                        <select name="progress" class="form-control">
                            <option value="all" {{ $progress === 'all' ? 'selected' : '' }}>{{__('All')}}</option>
                            <option value="not_delivered" {{ $progress === 'not_delivered' ? 'selected' : '' }}>{{__('Not yet delivered')}}</option>
                            <option value="in_progress" {{ $progress === 'in_progress' ? 'selected' : '' }}>{{__('In progress')}}</option>
                            <option value="partial" {{ $progress === 'partial' ? 'selected' : '' }}>{{__('Partially delivered')}}</option>
                            <option value="full" {{ $progress === 'full' ? 'selected' : '' }}>{{__('Fully delivered')}}</option>
                        </select>
                    </div>
                    <div class="col-md-1 mt-3">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" type="submit">{{__('db.submit')}}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-3"><div class="wrapper count-title"><div class="name"><strong>{{__('Sales in period')}}</strong></div><div class="count-number">{{$summary['sales']}}</div></div></div>
            <div class="col-sm-3"><div class="wrapper count-title"><div class="name"><strong>{{__('Not yet delivered')}}</strong></div><div class="count-number">{{$summary['not_delivered']}}</div></div></div>
            <div class="col-sm-3"><div class="wrapper count-title"><div class="name"><strong>{{__('In progress')}}</strong></div><div class="count-number">{{$summary['in_progress']}}</div></div></div>
            <div class="col-sm-3"><div class="wrapper count-title"><div class="name"><strong>{{__('Partially delivered')}}</strong></div><div class="count-number">{{$summary['partial']}}</div></div></div>
            <div class="col-sm-3 mt-3"><div class="wrapper count-title"><div class="name"><strong>{{__('Fully delivered')}}</strong></div><div class="count-number">{{$summary['full']}}</div></div></div>
            <div class="col-sm-3 mt-3"><div class="wrapper count-title"><div class="name"><strong>{{__('Qty sold')}}</strong></div><div class="count-number">{{\App\Services\DeliveryProgress::formatQty($summary['sold'])}}</div></div></div>
            <div class="col-sm-3 mt-3"><div class="wrapper count-title"><div class="name"><strong>{{__('Qty delivered')}}</strong></div><div class="count-number">{{\App\Services\DeliveryProgress::formatQty($summary['delivered'])}}</div></div></div>
            <div class="col-sm-3 mt-3"><div class="wrapper count-title"><div class="name"><strong>{{__('Qty remaining')}}</strong></div><div class="count-number">{{\App\Services\DeliveryProgress::formatQty($summary['remaining'])}}</div></div></div>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header"><h4>{{__('Sales and delivery progress')}}</h4></div>
            <div class="card-body table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.reference')}}</th>
                            <th>{{__('db.customer')}}</th>
                            <th>{{__('Progress')}}</th>
                            <th>{{__('Sold')}}</th>
                            <th>{{__('Delivered')}}</th>
                            <th>{{__('Remaining')}}</th>
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
                        <tr><td colspan="8">{{__('No sales match these filters.')}}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4>{{__('Products awaiting delivery')}}</h4></div>
            <div class="card-body table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{__('db.date')}}</th>
                            <th>{{__('db.Sale')}}</th>
                            <th>{{__('db.customer')}}</th>
                            <th>{{__('db.product')}}</th>
                            <th>{{__('db.Code')}}</th>
                            <th>{{__('Sold')}}</th>
                            <th>{{__('Delivered')}}</th>
                            <th>{{__('Remaining')}}</th>
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
                        <tr><td colspan="8">{{__('No product is waiting for delivery in this selection.')}}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4>{{__('Deliveries in the period')}}</h4></div>
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
                        <tr><td colspan="7">{{__('No delivery was recorded in this period.')}}</td></tr>
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
</script>
@endpush
