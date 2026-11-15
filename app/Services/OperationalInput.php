<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OperationalInput
{
    public function validate(Request $request, string $controller, string $method): void
    {
        if ($controller === 'TransferController' && in_array($method, ['store', 'update'])) {
            Validator::make($request->all(), [
                'from_warehouse_id' => 'required|integer|exists:warehouses,id',
                'to_warehouse_id' => 'required|integer|different:from_warehouse_id|exists:warehouses,id',
                'product_id' => 'required|array|min:1|exists:products,id', 'product_id.*' => 'required|integer',
                'qty' => 'required|array|min:1', 'qty.*' => ['required', 'numeric', 'gt:0', function ($attribute, $value, $fail) {
                    if (!is_finite((float) $value)) $fail('Enter a finite quantity.');
                }],
            ])->validate();
            if (array_keys($request->input('product_id')) !== array_keys($request->input('qty'))) {
                throw ValidationException::withMessages(['qty' => 'The product rows do not match.']);
            }
        }
        if (in_array($controller, ['SaleController', 'PurchaseController']) && in_array($method, ['store', 'update'])) {
            $rules = ['qty' => 'required|array|min:1', 'qty.*' => 'required|numeric|gt:0',
                'product_id' => 'required|array|min:1|exists:products,id', 'product_id.*' => 'required|integer',
                'subtotal' => 'required|array', 'subtotal.*' => 'required|numeric|min:0',
                'grand_total' => 'required|numeric|min:0', 'shipping_cost' => 'nullable|numeric|min:0',
                'order_tax_rate' => 'nullable|numeric|min:0', 'order_discount' => 'nullable|numeric|min:0',
                'coupon_discount' => 'nullable|numeric|min:0'];
            if ($controller === 'SaleController') {
                $rules += ['dqty' => 'nullable|array', 'dqty.*' => 'nullable|numeric|min:0',
                    'net_unit_price' => 'required|array', 'net_unit_price.*' => 'required|numeric|min:0'];
            } else {
                $rules += ['recieved' => 'nullable|array', 'recieved.*' => 'nullable|numeric|min:0'];
            }
            Validator::make($request->all(), $rules)->validate();
            foreach (array_keys($rules) as $field) {
                if (str_contains($field, '*')) {
                    continue;
                }
                foreach ((array) $request->input($field, []) as $value) {
                    if (is_numeric($value) && !is_finite((float) $value)) {
                        throw ValidationException::withMessages([$field => 'Enter a finite numeric value.']);
                    }
                }
            }
            $quantities = $request->input('qty');
            foreach (array_merge(['product_id', 'subtotal', $controller === 'SaleController' ? 'dqty' : 'recieved'], $controller === 'SaleController' ? ['net_unit_price'] : []) as $field) {
                $values = $request->input($field, []);
                if ($values && array_keys($values) !== array_keys($quantities)) {
                    throw ValidationException::withMessages([$field => 'The product rows do not match. Reload and review the order.']);
                }
            }
            $delivered = $request->input($controller === 'SaleController' ? 'dqty' : 'recieved', []);
            foreach ($delivered as $index => $value) {
                if ((float) $value > (float) $quantities[$index]) {
                    throw ValidationException::withMessages(['qty' => 'Delivered or received quantity cannot exceed ordered quantity.']);
                }
            }
            // Mirror the existing order calculation: order tax precedes coupon deduction.
            $subtotal = array_sum(array_map('floatval', $request->input('subtotal')));
            $discount = (float) $request->input('order_discount', 0);
            $tax = ($subtotal - $discount) * (float) $request->input('order_tax_rate', 0) / 100;
            $expected = $subtotal - $discount + $tax + (float) $request->input('shipping_cost', 0)
                - (float) $request->input('coupon_discount', 0);
            $precision = max(0, min(6, (int) config('decimal', 2)));
            if (abs(round($expected, $precision) - round((float) $request->input('grand_total'), $precision)) > pow(10, -$precision) + 0.000001) {
                throw ValidationException::withMessages(['grand_total' => 'Order totals changed. Review quantities, discounts and taxes before saving.']);
            }
        }
        if (in_array($method, ['addPayment', 'updatePayment', 'clearDue', 'addDeposit', 'updateDeposit'])) {
            $amount = $method === 'updatePayment' ? 'edit_amount' : 'amount';
            $tendered = $method === 'updatePayment' ? 'edit_paying_amount' : 'paying_amount';
            Validator::make($request->all(), [$amount => 'required|numeric|gt:0', $tendered => 'nullable|numeric|min:0'])->validate();
            foreach ([$amount, $tendered] as $field) {
                if ($request->filled($field) && !is_finite((float) $request->input($field))) {
                    throw ValidationException::withMessages([$field => 'Enter a finite numeric value.']);
                }
            }
        }
    }
}
