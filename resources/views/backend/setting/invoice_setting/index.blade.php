@extends('backend.layout.main')
@push('css')
    <style>
        #table-loader {
            background-color: #f9f9f9;
        }
    </style>
@endpush
@section('content')
    @include('includes.session_message')
    
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>{{ __('db.Invoice Settings') }}</h2>
        </div>

        <div class=" mb-3 ">
            <a class="btn btn-primary float-end" href="{{ route('settings.invoice.create') }}"> <i class="dripicons-plus"></i>
                {{ __('db.Add New Invoice Setting') }}</a>
        </div>



        <table class="table table-bordered">
            <thead class="table-secondary">
                <tr>
                    <th class="col-3">{{ __('db.Template Name') }}</th>
                    <th class="col-2">{{ __('db.Size') }}</th>
                    <th class="col-2  text-center">{{ __('db.Default') }}</th>
                    <th class="col-3 text-center">{{ __('db.action') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr id="table-loader" style="display: none;">
                    <td colspan="5" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden"></span>
                        </div>
                    </td>
                </tr>
                @forelse ($invoiceSettings as $invoice)
                    <tr>
                        <td>{{ $invoice->template_name }}</td>
                        <td>{{ $invoice->size }}</td>
                        <td class="text-center align-middle">
                            @if ($invoice->is_default)
                                <span class="badge bg-success">{{ __('db.Default')}}</span>
                            @else
                                <button class="btn btn-sm btn-outline-secondary change-status" data-id="{{ $invoice->id }}"
                                    data-column="is_default" data-url="{{ route('settings.invoice.update', $invoice->id) }}">
                                    {{ __('db.Set Default')}}
                                </button>
                            @endif
                        </td>



                        <td class="text-center align-middle">
                            <a class="btn btn-warning btn-sm"
                                href="{{ route('settings.invoice.edit', $invoice->id) }}"></i>{{ __('db.update')}}</a>
                            <button class="btn btn-danger btn-sm delete-invoice" data-id="{{ $invoice->id }}"
                                data-url="{{ route('settings.invoice.destroy', $invoice->id) }}">{{ __('db.delete')}}</button>
                            {{-- <a href="{{ route('settings.invoice.show', $invoice->id) }}" class="btn btn-outline-primary">Show
                            </a> --}}
                        </td>
                    </tr>
                @empty
                @endforelse

            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('.delete-invoice').on('click', function() {
                var button = $(this);
                var id = button.data('id');
                var url = button.data('url');
                var row = button.closest('tr');

                if (confirm('Are you sure you want to delete this invoice setting?')) {
                    $('#table-loader').show(); // Show loader row

                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            _method: 'DELETE',
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success == true) {
                                row.fadeOut(400, function() {
                                    $(this).remove();
                                });
                            }else if (response.success == false){
                                alert('Default invoice cannot be deleted');
                            } else {
                                alert(response.not_permitted);
                            }

                        },
                        error: function() {
                            alert('Error deleting invoice.');
                        },
                        complete: function() {
                            $('#table-loader').hide();
                        }
                    });
                }
            });

            $('.change-status').on('click', function() {
                var button = $(this);
                var id = button.data('id');
                var url = button.data('url');
                var column = button.data('column');
                console.log(id, url, column)
                if (confirm('Are you sure you want to change the status?')) {
                    $.ajax({
                        url: url,
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'PUT', // important if you're using Laravel's resourceful routes
                            id: id,
                            column: column
                        },
                        success: function() {
                            location.reload();
                        },
                        error: function() {
                            alert("Failed to update status.");
                        }
                    });
                }
            })

        });
    </script>
@endpush
