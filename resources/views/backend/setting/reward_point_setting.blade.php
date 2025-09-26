@extends('backend.layout.main') @section('content')

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{__('db.Reward Point Setting')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'setting.rewardPointStore', 'files' => true, 'method' => 'post']) !!}
                            <div class="row">
                                <div class="col-md-3 mt-3">
                                    <div class="form-group">
                                        @if($lims_reward_point_setting_data && $lims_reward_point_setting_data->is_active)
                                        <input type="checkbox" name="is_active" value="1" checked>
                                        @else
                                        <input type="checkbox" name="is_active" value="1">
                                        @endif &nbsp;
                                        <label>{{__('db.Active reward point')}}</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{__('db.Sold amount per point')}} *</label> <i class="dripicons-question" data-toggle="tooltip" title="{{__('db.This means how much point customer will get according to sold amount For example, if you put 100 then for every 100 dollar spent customer will get one point as reward')}}"></i>
                                        <input type="number" name="per_point_amount" class="form-control" value="@if($lims_reward_point_setting_data){{$lims_reward_point_setting_data->per_point_amount}}@endif" required />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{__('db.Minumum sold amount to get point')}} * <i class="dripicons-question" data-toggle="tooltip" title="{{__('db.For example, if you put 100 then customer will only get point after spending 100 dollar or more')}}"></i></label>
                                        <input type="number" name="minimum_amount" class="form-control" value="@if($lims_reward_point_setting_data){{$lims_reward_point_setting_data->minimum_amount}}@endif" required />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{__('db.Point Expiry Duration')}}</label>
                                        <input type="number" name="duration" class="form-control" value="@if($lims_reward_point_setting_data){{$lims_reward_point_setting_data->duration}}@endif" />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>{{__('db.Duration Type')}}</label>
                                        <select name="type" class="form-control">
                                            @if($lims_reward_point_setting_data && $lims_reward_point_setting_data->type == 'Year')
                                                <option selected value="Year">Years</option>
                                                <option value="Month">Months</option>
                                            @else
                                                <option value="Year">Years</option>
                                                <option selected value="Month">Months</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12 form-group">
                                    <button type="submit" class="btn btn-primary">{{__('db.submit')}}</button>
                                </div>
                            </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #reward-point-setting-menu").addClass("active");

    $('[data-toggle="tooltip"]').tooltip();

</script>
@endpush
