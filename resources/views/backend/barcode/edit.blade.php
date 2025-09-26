@extends('backend.layout.main')
@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{__('db.Edit Barcode Sticker Setting')}}</h4>
                    </div>
                    <div class="card-body">
                        {!! Form::open(['url' => action([\App\Http\Controllers\BarcodeController::class, 'update'], [$barcode->id]), 'method' => 'PUT',
                            'id' => 'add_barcode_settings_form' ]) !!}
                            <div class="box box-solid">
                                <div class="box-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                    <div class="form-group">
                                        {!! Form::label('name', __('db.Sticker Sheet setting Name') . ':*') !!}
                                        {!! Form::text('name', $barcode->name, ['class' => 'form-control', 'required',
                                        'placeholder' => __('db.Sticker Sheet setting Name')]); !!}
                                    </div>
                                    </div>
                                    <div class="col-sm-12">
                                    <div class="form-group">
                                        {!! Form::label('description', __('db.Sticker Sheet setting Description') ) !!}
                                        {!! Form::textarea('description', $barcode->description, ['class' => 'form-control',
                                        'placeholder' => __('db.Sticker Sheet setting Description'), 'rows' => 3]); !!}
                                    </div>
                                    </div>
                                    <div class="col-sm-12">
                                    <div class="form-group">
                                        <div class="">
                                        <label>
                                            {!! Form::checkbox('is_continuous', 1, $barcode->is_continuous, ['id' => 'is_continuous']); !!} @lang('db.Continuous feed or rolls')</label>
                                        </div>
                                    </div>
                                    </div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('top_margin', __('db.Additional top margin') . ' ('. __('db.In Inches') . '):*') !!}
                                        <div class="input-group">
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-arrow-up" aria-hidden="true"></span>
                                        </span>
                                        {!! Form::number('top_margin', $barcode->top_margin, ['class' => 'form-control',
                                        'placeholder' => __('db.top_margin'), 'min' => 0, 'step' => 0.00001, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('left_margin', __('db.Additional left margin') . ' ('. __('db.In Inches') . '):*') !!}
                                        <div class="input-group">
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span>
                                        </span>
                                        {!! Form::number('left_margin', $barcode->left_margin, ['class' => 'form-control',
                                        'placeholder' => __('db.Additional left margin'), 'min' => 0, 'step' => 0.00001, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('width', __('db.Width of sticker') . ' ('. __('db.In Inches') . '):*') !!}
                                        <div class="input-group">

                                        {!! Form::number('width', $barcode->width, ['class' => 'form-control',
                                        'placeholder' => __('db.Width of sticker'), 'min' => 0.1, 'step' => 0.00001, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('height', __('db.Height of sticker') . ' ('. __('db.In Inches') . '):*') !!}
                                        <div class="input-group">

                                        {!! Form::number('height', $barcode->height, ['class' => 'form-control',
                                            'placeholder' => __('db.Barcode Height'), 'min' => 0.1, 'step' => 0.00001, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('paper_width', __('db.Paper width') . ' ('. __('db.In Inches') . '):*') !!}
                                        <div class="input-group">

                                        {!! Form::number('paper_width', $barcode->paper_width, ['class' => 'form-control',
                                        'placeholder' => __('db.Paper width'), 'min' => 0.1, 'step' => 0.00001, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div id="paper-height-section" class="col-sm-6 paper_height_div">
                                    <div class="form-group">
                                        {!! Form::label('paper_height', __('db.Paper height') . ' ('. __('db.In Inches') . '):*') !!}
                                        <div class="input-group">

                                        {!! Form::number('paper_height', $barcode->paper_height, ['class' => 'form-control',
                                        'placeholder' => __('db.Paper height'), 'min' => 0.1, 'step' => 0.00001, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('stickers_in_one_row', __('db.Stickers in one row') . ':*') !!}
                                        <div class="input-group">

                                        {!! Form::number('stickers_in_one_row', $barcode->stickers_in_one_row, ['class' => 'form-control',
                                        'placeholder' => __('db.Stickers in one row'), 'min' => 1, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('row_distance', __('db.Distance between two rows') . ' ('. __('db.In Inches') . '):*') !!}
                                        <div class="input-group">

                                        {!! Form::number('row_distance', $barcode->row_distance, ['class' => 'form-control',
                                        'placeholder' => __('db.Distance between two rows'), 'min' => 0, 'step' => 0.00001, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('col_distance', __('db.Distance between two columns') . ' ('. __('db.In Inches') . '):*') !!}
                                        <div class="input-group">

                                        {!! Form::number('col_distance', $barcode->col_distance, ['class' => 'form-control',
                                        'placeholder' => __('db.Distance between two columns'), 'min' => 0, 'step' => 0.00001, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="col-sm-6 stickers_per_sheet_div">
                                    <div class="form-group">
                                        {!! Form::label('stickers_in_one_sheet', __('db.No of Stickers per sheet') . ':*') !!}
                                        <div class="input-group">

                                        {!! Form::number('stickers_in_one_sheet', $barcode->stickers_in_one_sheet, ['class' => 'form-control',
                                        'placeholder' => __('db.No of Stickers per sheet'), 'min' => 1, 'required']); !!}
                                        </div>
                                    </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="col-sm-6 stickers_per_sheet_div @if( $barcode->is_continuous ) {{ 'hide' }} @endif">
                                        <div class="form-group">
                                          {!! Form::label('stickers_in_one_sheet', __('db.barcode_stickers_in_one_sheet') . ':*') !!}
                                          <div class="input-group">
                                            {!! Form::number('stickers_in_one_sheet', $barcode->stickers_in_one_sheet, ['class' => 'form-control',
                                            'placeholder' => __('db.Barcode Stickers In One Sheet'), 'min' => 1, 'required']); !!}
                                          </div>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="col-sm-6"></div>
                                    <div class="col-sm-6">
                                    <div class="form-group">
                                        <div class="">
                                        <label>
                                            {!! Form::checkbox('is_default', 1, $barcode->is_default); !!} @lang('db.Set as default')</label>
                                        </div>
                                    </div>
                                    </div>
                                    <div class="clearfix"></div>

                                    <div class="col-sm-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-big">{{ __('db.update') }}</button>
                                    </div>
                                </div>
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
<script>
    $(document).ready(function () {
        const isContinuousInput = $("input[name='is_continuous']");
        const paperHeightSection = $("#paper-height-section");
        const paperHeightInput = $("input[name='paper_height']");

        const togglePaperHeight = () => {
            if (isContinuousInput.is(":checked")) {
                paperHeightInput.val(0);
                paperHeightInput.prop("disabled", true);
            } else {
                paperHeightInput.prop("disabled", false);
            }
        };

        togglePaperHeight();

        isContinuousInput.on("change", togglePaperHeight);
    });
</script>
@endpush

