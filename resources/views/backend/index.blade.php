@extends('backend.layout.main')
@section('content')

<x-success-message key="message" />
<x-error-message key="not_permitted" />

      @php
        if($general_setting->theme == 'default.css') {
          $color = '#733686';
          $color_rgba = 'rgba(115, 54, 134, 0.8)';
        }
        elseif($general_setting->theme == 'green.css') {
            $color = '#2ecc71';
            $color_rgba = 'rgba(46, 204, 113, 0.8)';
        }
        elseif($general_setting->theme == 'blue.css') {
            $color = '#3498db';
            $color_rgba = 'rgba(52, 152, 219, 0.8)';
        }
        elseif($general_setting->theme == 'dark.css'){
            $color = '#34495e';
            $color_rgba = 'rgba(52, 73, 94, 0.8)';
        }
      @endphp
      <div class="row">

        <div class="container-fluid">
          @php
            $lims_warehouse_list = App\Models\Warehouse::where('is_active', true)->get();
          @endphp

          @if(!config('database.connections.saleprosaas_landlord') && \Auth::user()->role_id <= 2)
            @if(isset($versionUpgradeData['alert_version_upgrade_enable']) && $versionUpgradeData['alert_version_upgrade_enable']==true)
            <div id="alertSection" class="alert not-slide alert-primary alert-dismissible fade show" role="alert">
                <p id="announce"><strong>Announce !!!</strong> A new version {{$versionUpgradeData['demo_version']}} has been released. Please <i><b><a href="{{route('new-release')}}">Click here</a></b></i> to check upgrade details.</p>
                <button type="button" id="closeButtonUpgrade" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            @endif
          @endif
          <div class="col-md-12">
            <div class="brand-text float-left mt-4">
                <h3>{{__('db.welcome')}} <span>{{Auth::user()->name}}</span></h3>
            </div>
            @if(in_array('restaurant',explode(',',cache()->get('general_setting')->modules)))
              @if(Auth::user()->role_id > 2 && isset(Auth::user()->service_staff))
                  @php
                    $cooked = DB::table('sales')->where('waiter_id', Auth::user()->id)->where('sale_status', 5)->orWhere('sale_status', 6)->where('sales.created_at', '>=', now()->subDay())->count();
                  @endphp
              @elseif(Auth::user()->role_id <= 2)
                  @php
                    $cooked = DB::table('sales')->where('sale_status', 6)->where('sales.created_at', '>=', now()->subDay())->count();
                  @endphp
              @endif
            @endif
            @if(in_array('restaurant',explode(',',cache()->get('general_setting')->modules)))
            <a href="{{route('kitchen.dashboard')}}">
              <div class="alert alert-warning alert-dismissible text-center mb-2">
                <strong>{{$cooked}} {{__('db.Orders to serve')}}</strong>
              </div>
            </a>
            @endif

            @php
              $revenue_profit_summary = $role_has_permissions_list->where('name', 'revenue_profit_summary')->first();
            @endphp
            @if($revenue_profit_summary)
            <div class="filter-toggle btn-group d-inline-block">
              @if (\Auth::user()->role_id <= 2)
              <select name="warehouse_id" class="selectpicker" id="warehouse_btn" data-live-search="true" data-live-search-style="begins" >
                  <option value="0">{{__('db.All Warehouse')}}</option>
                  @foreach($lims_warehouse_list as $warehouse)
                      <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                  @endforeach
              </select>
              @endif

              <div class="dropdown d-inline">
                <button type="button" class="btn btn-defualt dropdown-toggle" data-toggle="dropdown">
                {{__('db.Date Range')}}
                </button>
                <div class="dropdown-menu">
                  <button class="btn btn-default date-btn w-100" style="border:none" data-start_date="{{date('Y-m-d')}}" data-end_date="{{date('Y-m-d')}}">{{__('db.Today')}}</button>
                  <button class="btn btn-default date-btn w-100" style="border:none" data-start_date="{{date('Y-m-d', strtotime(' -7 day'))}}" data-end_date="{{date('Y-m-d')}}">{{__('db.Last 7 Days')}}</button>
                  <button class="btn btn-default date-btn w-100 active" style="border:none" data-start_date="{{date('Y').'-'.date('m').'-'.'01'}}" data-end_date="{{date('Y-m-d')}}">{{__('db.This Month')}}</button>
                  <button class="btn btn-default date-btn w-100" style="border:none" data-start_date="{{date('Y').'-01'.'-01'}}" data-end_date="{{date('Y').'-12'.'-31'}}">{{__('db.This Year')}}</button>
                </div>
              </div>
            </div>
            @endif
          </div>
        </div>
      </div>
      <!-- Counts Section -->
      <section class="dashboard-counts">
        <div class="container-fluid">
          <div class="row">
            @if($revenue_profit_summary)
            <div class="col-md-12 form-group">
              <div class="row">
                <!-- Count item widget-->
                <div class="col-sm-3">
                  <div class="wrapper count-title">
                    <div class="icon"><i class="dripicons-graph-bar" style="color: #733686"></i></div>
                    <div>
                        <div class="count-number revenue-data">{{number_format((float)$revenue,$general_setting->decimal, '.', '')}}</div>
                        <div class="name"><strong style="color: #733686">{{ __('db.revenue') }}</strong></div>
                    </div>
                  </div>
                </div>
                <!-- Count item widget-->
                <div class="col-sm-3">
                  <div class="wrapper count-title">
                    <div class="icon"><i class="dripicons-return" style="color: #ff8952"></i></div>
                    <div>
                        <div class="count-number return-data">{{number_format((float)$return,$general_setting->decimal, '.', '')}}</div>
                        <div class="name"><strong style="color: #ff8952">{{__('db.Sale Return')}}</strong></div>
                    </div>
                  </div>
                </div>
                <!-- Count item widget-->
                <div class="col-sm-3">
                  <div class="wrapper count-title">
                    <div class="icon"><i class="dripicons-media-loop" style="color: #00c689"></i></div>
                    <div>
                        <div class="count-number purchase_return-data">{{number_format((float)$purchase_return,$general_setting->decimal, '.', '')}}</div>
                        <div class="name"><strong style="color: #00c689">{{__('db.Purchase Return')}}</strong></div>
                    </div>
                  </div>
                </div>
                <!-- Count item widget-->
                <div class="col-sm-3">
                  <div class="wrapper count-title">
                    <div class="icon"><i class="dripicons-trophy" style="color: #297ff9"></i></div>
                    <div>
                        <div class="count-number profit-data">{{number_format((float)$profit,$general_setting->decimal, '.', '')}}</div>
                        <div class="name"><strong style="color: #297ff9">{{__('db.profit')}}</strong></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @php
              $cash_flow = $role_has_permissions_list->where('name', 'cash_flow')->first();
            @endphp
            @if($cash_flow)
            <div class="col-md-7 mt-4">
              <div class="card line-chart-example">
                <div class="card-header d-flex align-items-center">
                  <h4>{{__('db.Cash Flow')}}</h4>
                </div>
                <div class="card-body">
                  <canvas id="cashFlow" data-color = "{{$color}}" data-color_rgba = "{{$color_rgba}}" data-recieved = "{{json_encode($payment_recieved)}}" data-sent = "{{json_encode($payment_sent)}}" data-month = "{{json_encode($month)}}" data-label1="{{__('db.Payment Recieved')}}" data-label2="{{__('db.Payment Sent')}}"></canvas>
                </div>
              </div>
            </div>
            @endif
            @php
              $monthly_summary = $role_has_permissions_list->where('name', 'monthly_summary')->first();
            @endphp
            @if($monthly_summary)
            <div class="col-md-5 mt-4">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{date('F')}} {{date('Y')}}</h4>
                </div>
                <div class="pie-chart mb-2">
                    <canvas id="transactionChart" data-color = "{{$color}}" data-color_rgba = "{{$color_rgba}}" data-revenue={{$revenue}} data-purchase={{$purchase}} data-expense={{$expense}} data-label1="{{__('db.Purchase')}}" data-label2="{{__('db.revenue')}}" data-label3="{{__('db.Expense')}}" width="100" height="95"> </canvas>
                </div>
              </div>
            </div>
            @endif
          </div>
        </div>

        <div class="container-fluid">
          <div class="row">
            @php
              $yearly_report = $role_has_permissions_list->where('name', 'yearly_report')->first();
            @endphp
            @if($yearly_report)
            <div class="col-md-12">
              <div class="card">
                <div class="card-header d-flex align-items-center">
                  <h4>{{__('db.yearly report')}}</h4>
                </div>
                <div class="card-body">
                  <canvas id="saleChart" data-sale_chart_value = "{{json_encode($yearly_sale_amount)}}" data-purchase_chart_value = "{{json_encode($yearly_purchase_amount)}}" data-label1="{{__('db.Purchased Amount')}}" data-label2="{{__('db.Sold Amount')}}"></canvas>
                </div>
              </div>
            </div>
            @endif
            <div class="col-md-7">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{__('db.Recent Transaction')}}</h4>
                  <div class="right-column">
                    <div class="badge badge-primary">{{__('db.latest')}} 5</div>
                  </div>
                </div>
                <ul class="nav nav-tabs" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" href="#sale-latest" role="tab" data-toggle="tab">{{__('db.Sale')}}</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#purchase-latest" role="tab" data-toggle="tab">{{__('db.Purchase')}}</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#quotation-latest" role="tab" data-toggle="tab">{{__('db.Quotation')}}</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#payment-latest" role="tab" data-toggle="tab">{{__('db.Payment')}}</a>
                  </li>
                </ul>

                <div class="tab-content">
                  <div role="tabpanel" class="tab-pane fade show active" id="sale-latest">
                      <div class="table-responsive">
                        <table id="recent-sale" class="table">
                          <thead>
                            <tr>
                              <th>{{__('db.date')}}</th>
                              <th>{{__('db.reference')}}</th>
                              <th>{{__('db.customer')}}</th>
                              <th>{{__('db.status')}}</th>
                              <th>{{__('db.grand total')}}</th>
                            </tr>
                          </thead>
                          <tbody>

                          </tbody>
                        </table>
                      </div>
                  </div>
                  <div role="tabpanel" class="tab-pane fade" id="purchase-latest">
                      <div class="table-responsive">
                        <table id="recent-purchase" class="table">
                          <thead>
                            <tr>
                              <th>{{__('db.date')}}</th>
                              <th>{{__('db.reference')}}</th>
                              <th>{{__('db.Supplier')}}</th>
                              <th>{{__('db.status')}}</th>
                              <th>{{__('db.grand total')}}</th>
                            </tr>
                          </thead>
                          <tbody>

                          </tbody>
                        </table>
                      </div>
                  </div>
                  <div role="tabpanel" class="tab-pane fade" id="quotation-latest">
                      <div class="table-responsive">
                        <table id="recent-quotation" class="table">
                          <thead>
                            <tr>
                              <th>{{__('db.date')}}</th>
                              <th>{{__('db.reference')}}</th>
                              <th>{{__('db.customer')}}</th>
                              <th>{{__('db.status')}}</th>
                              <th>{{__('db.grand total')}}</th>
                            </tr>
                          </thead>
                          <tbody>
                          </tbody>
                        </table>
                      </div>
                  </div>
                  <div role="tabpanel" class="tab-pane fade" id="payment-latest">
                      <div class="table-responsive">
                        <table id="recent-payment" class="table">
                          <thead>
                            <tr>
                              <th>{{__('db.date')}}</th>
                              <th>{{__('db.reference')}}</th>
                              <th>{{__('db.Amount')}}</th>
                              <th>{{__('db.Paid By')}}</th>
                            </tr>
                          </thead>
                          <tbody>
                          </tbody>
                        </table>
                      </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-5">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{__('db.Best Seller').' '.date('F')}}</h4>
                  <div class="right-column">
                    <div class="badge badge-primary">{{__('db.top')}} 5</div>
                  </div>
                </div>
                <div class="table-responsive">
                    <table id="monthly-best-selling-qty" class="table">
                      <thead>
                        <tr>
                          <th>{{__('db.Product Details')}}</th>
                          <th>{{__('db.qty')}}</th>
                        </tr>
                      </thead>
                      <tbody>

                      </tbody>
                    </table>
                  </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{__('db.Best Seller').' '.date('Y'). '('.__('db.qty').')'}}</h4>
                  <div class="right-column">
                    <div class="badge badge-primary">{{__('db.top')}} 5</div>
                  </div>
                </div>
                <div class="table-responsive">
                    <table id="yearly-best-selling-qty" class="table">
                      <thead>
                        <tr>
                          <th>{{__('db.Product Details')}}</th>
                          <th>{{__('db.qty')}}</th>
                        </tr>
                      </thead>
                      <tbody>

                      </tbody>
                    </table>
                  </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{__('db.Best Seller') . ' ' . date('Y') . '(' . __('db.Price') . ')'}}</h4>
                  <div class="right-column">
                    <div class="badge badge-primary">{{__('db.top')}} 5</div>
                  </div>
                </div>
                <div class="table-responsive">
                    <table id="yearly-best-selling-price" class="table">
                      <thead>
                        <tr>
                          <th>{{__('db.Product Details')}}</th>
                          <th>{{__('db.grand total')}}</th>
                        </tr>
                      </thead>
                      <tbody>

                      </tbody>
                    </table>
                  </div>
              </div>
            </div>
          </div>
        </div>
      </section>


@endsection

@push('scripts')
<script type="text/javascript">
    $(document).ready(function(){
      $.ajax({
        url: '{{url("/yearly-best-selling-price")}}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            var url = '{{url("/images/product")}}';
            data.forEach(function(item){
              if(item.product_images)
                var images = item.product_images.split(',');
              else
                var images = ['zummXD2dvAtI.png'];
              $('#yearly-best-selling-price').find('tbody').append('<tr><td><div class="d-flex align-items-center"><img src="'+url+'/'+images[0]+'" width="30" height="25" class="ml-3 mr-3"> '+item.product_name+' ['+item.product_code+']</div></td><td>'+item.total_price+'</td></tr>');
            })
        }
      });
    });

    $(document).ready(function(){
      $.ajax({
        url: '{{url("/yearly-best-selling-qty")}}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            var url = '{{url("/images/product")}}';
            data.forEach(function(item){
              if(item.product_images)
                var images = item.product_images.split(',');
              else
                var images = ['zummXD2dvAtI.png'];
              $('#yearly-best-selling-qty').find('tbody').append('<tr><td><div class="d-flex align-items-center"><img src="'+url+'/'+images[0]+'" width="30" height="25" class="ml-3 mr-3"> '+item.product_name+' ['+item.product_code+']</div></td><td>'+item.sold_qty+'</td></tr>');
            })
        }
      });
    });

    $(document).ready(function(){
      $.ajax({
        url: '{{url("/monthly-best-selling-qty")}}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            var url = '{{url("/images/product")}}';
            data.forEach(function(item){
              if(item.product_images)
                var images = item.product_images.split(',');
              else
                var images = ['zummXD2dvAtI.png'];
              $('#monthly-best-selling-qty').find('tbody').append('<tr><td><div class="d-flex align-items-center"><img src="'+url+'/'+images[0]+'" width="30" height="25" class="ml-3 mr-3"> '+item.product_name+' ['+item.product_code+']</div></td><td>'+item.sold_qty+'</td></tr>');
            })
        }
      });
    });

    $(document).ready(function(){
      $.ajax({
        url: '{{url("/recent-sale")}}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            data.forEach(function(item){
              var sale_date = dateFormat(item.created_at.split('T')[0], '{{$general_setting->date_format}}')
              if(item.sale_status == 1){
                var status = '<div class="badge badge-success">{{__("db.Completed")}}</div>';
              } else if(item.sale_status == 2) {
                var status = '<div class="badge badge-danger">{{__("db.Pending")}}</div>';
              } else {
                var status = '<div class="badge badge-warning">{{__("db.Draft")}}</div>';
              }
              $('#recent-sale').find('tbody').append('<tr><td>'+sale_date+'</td><td>'+item.reference_no+'</td><td>'+item.name+'</td><td>'+status+'</td><td>'+item.grand_total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</td></tr>');
            })
        }
      });
    });

    $(document).ready(function(){
      $.ajax({
        url: '{{url("/recent-purchase")}}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            data.forEach(function(item){
              var payment_date = dateFormat(item.created_at.split('T')[0], '{{$general_setting->date_format}}')
              if(item.status == 1){
                var status = '<div class="badge badge-success">{{__("db.Recieved")}}</div>';
              }
              else if(item.status == 2) {
                var status = '<div class="badge badge-danger">{{__("db.Partial")}}</div>';
              }
              else if(item.status == 3) {
                var status = '<div class="badge badge-danger">{{__("db.Pending")}}</div>';
              }
              else {
                var status = '<div class="badge badge-warning">{{__("db.Ordered")}}</div>';
              }
              $('#recent-purchase').find('tbody').append('<tr><td>'+payment_date+'</td><td>'+item.reference_no+'</td><td>'+item.name+'</td><td>'+status+'</td><td>'+item.grand_total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</td></tr>');
            })
        }
      });
    });

    $(document).ready(function(){
      $.ajax({
        url: '{{url("/recent-quotation")}}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            data.forEach(function(item){
              var quotation_date = dateFormat(item.created_at.split('T')[0], '{{$general_setting->date_format}}')
              if(item.quotation_status == 1){
                var status = '<div class="badge badge-success">{{__("db.Pending")}}</div>';
              }
              else if(item.quotation_status == 2) {
                var status = '<div class="badge badge-danger">{{__("db.Sent")}}</div>';
              }
              $('#recent-quotation').find('tbody').append('<tr><td>'+quotation_date+'</td><td>'+item.reference_no+'</td><td>'+item.name+'</td><td>'+status+'</td><td>'+item.grand_total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</td></tr>');
            })
        }
      });
    });

    $(document).ready(function(){
      $.ajax({
        url: '{{url("/recent-payment")}}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            data.forEach(function(item){
              var payment_date = dateFormat(item.created_at.split('T')[0], '{{$general_setting->date_format}}')
              $('#recent-payment').find('tbody').append('<tr><td>'+payment_date+'</td><td>'+item.payment_reference+'</td><td>'+item.amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</td><td>'+item.paying_method+'</td></tr>');
            })
        }
      });
    });

    function dateFormat(inputDate, format) {
        const date = new Date(inputDate);
        //extract the parts of the date
        const day = date.getDate();
        const month = date.getMonth() + 1;
        const year = date.getFullYear();
        //replace the month
        format = format.replace("m", month.toString().padStart(2,"0"));
        //replace the year
        format = format.replace("Y", year.toString());
        //replace the day
        format = format.replace("d", day.toString().padStart(2,"0"));
        return format;
    }


    $(document).ready(function(){
      $.ajax({
        url: '{{url("/")}}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            $('#userShowModal').modal('show');
            $('#user-id').text(data.id);
            $('#user-name').text(data.name);
            $('#user-email').text(data.email);
        }
      });
    })
    // Show and hide color-switcher
    $(".color-switcher .switcher-button").on('click', function() {
        $(".color-switcher").toggleClass("show-color-switcher", "hide-color-switcher", 300);
    });

    // Color Skins
    $('a.color').on('click', function() {
        /*var title = $(this).attr('title');
        $('#style-colors').attr('href', 'css/skin-' + title + '.css');
        return false;*/
        $.get('setting/general_setting/change-theme/' + $(this).data('color'), function(data) {
        });
        var style_link= $('#custom-style').attr('href').replace(/([^-]*)$/, $(this).data('color') );
        $('#custom-style').attr('href', style_link);
    });

    $(".date-btn").on("click", function() {
        $(".date-btn").removeClass("active");
        $(this).addClass("active");
        var start_date = $(this).data('start_date');
        var end_date = $(this).data('end_date');
        var warehouse_id = $("#warehouse_btn").val();
        $.get('dashboard-filter/' + start_date + '/' + end_date + '/' + warehouse_id, function(data) {
            dashboardFilter(data);
        });
    });

    $("#warehouse_btn").on("change", function() {
        var warehouse_id = $(this).val();
        var start_date = $('.date-btn.active').data('start_date');
        var end_date = $('.date-btn.active').data('end_date');
        //console.log(start_date);
        //console.log(end_date);
        $.get('dashboard-filter/' + start_date + '/' + end_date + '/' + warehouse_id, function(data) {
            dashboardFilter(data);
        });
    });

    function dashboardFilter(data){
        $('.revenue-data').hide();
        $('.revenue-data').html(parseFloat(data[0]).toFixed({{$general_setting->decimal}}));
        $('.revenue-data').show(500);

        $('.return-data').hide();
        $('.return-data').html(parseFloat(data[1]).toFixed({{$general_setting->decimal}}));
        $('.return-data').show(500);

        $('.profit-data').hide();
        $('.profit-data').html(parseFloat(data[2]).toFixed({{$general_setting->decimal}}));
        $('.profit-data').show(500);

        $('.purchase_return-data').hide();
        $('.purchase_return-data').html(parseFloat(data[3]).toFixed({{$general_setting->decimal}}));
        $('.purchase_return-data').show(500);
    }
</script>
@endpush
