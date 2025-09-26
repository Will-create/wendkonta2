<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PurchaseRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|exists:warehouses,id',
            'currency_id' => 'required',
            'exchange_rate' => 'required|numeric', // Ensures exchange_rate is a valid number
            'product_code' => 'required|array', // Ensures product_code is an array
            'product_code.*' => 'required|string', // Ensures each product_code value is a string
            'qty' => 'required|array', // Ensures qty is an array
            'qty.*' => 'required|integer|min:1', // Each qty must be a valid integer and at least 1
            'document' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
        ];
    }
    
    public function messages()
    {
        return [
            'warehouse_id.required' => 'select a warehouse',
            'currency_id.required' => 'currency field is required.',
            'exchange_rate.required' => 'The exchange rate is required.',
            'exchange_rate.numeric' => 'The exchange rate must be a valid number.',
            'product_code.required' => 'Please insert a product.',
            'qty.required' => 'At least one quantity is required.',
            'qty.array' => 'The quantities must be in an array format.',
            'qty.*.required' => 'Each quantity must be provided.',
            'qty.*.integer' => 'Each quantity must be a valid number.',
            'qty.*.min' => 'Each quantity must be at least 1.',
        ];
    }
}
