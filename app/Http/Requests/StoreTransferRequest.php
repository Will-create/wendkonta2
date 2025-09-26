<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_warehouse_id'   => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id',
            'qty'        => 'required|min:1',
            'document'       => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
        ];
    }
    
    public function messages(): array
    {
        return [
            'from_warehouse_id.required'   => 'Please select From warehouse.',
            'to_warehouse_id.required'   => 'Please select From warehouse.',
            'qty.required'           => 'Please add at least one product.',
            'document.mimes'          => 'The document must be a file of type: jpg, jpeg, png, gif, pdf, csv, docx, xlsx, txt.',
        ];
    }
}
