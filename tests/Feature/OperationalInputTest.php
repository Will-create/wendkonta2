<?php

namespace Tests\Feature;

use App\Services\OperationalInput;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('products', function (Blueprint $table) { $table->id(); });
        DB::table('products')->insert(['id' => 1]);
    }

    private function sale(array $changes = []): Request
    {
        config(['decimal' => 2]);
        return Request::create('/sales', 'POST', array_replace([
            'product_id' => [1], 'qty' => [0.5], 'dqty' => [0.25], 'net_unit_price' => [50], 'subtotal' => [25],
            'order_discount' => 5, 'order_tax_rate' => 10, 'shipping_cost' => 2,
            'coupon_discount' => 3, 'grand_total' => 21,
        ], $changes));
    }

    public function test_fractional_units_and_existing_order_tax_coupon_sequence_are_accepted(): void
    {
        app(OperationalInput::class)->validate($this->sale(), 'SaleController', 'store');
        $this->assertTrue(true);
    }

    public function test_changed_grand_total_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalInput::class)->validate($this->sale(['grand_total' => 1]), 'SaleController', 'store');
    }

    public function test_delivery_cannot_exceed_ordered_quantity(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalInput::class)->validate($this->sale(['dqty' => [1]]), 'SaleController', 'store');
    }

    public function test_mismatched_product_rows_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalInput::class)->validate($this->sale(['subtotal' => [25, 30]]), 'SaleController', 'store');
    }

    public function test_negative_payment_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalInput::class)->validate(Request::create('/sales/add_payment', 'POST', ['amount' => -1]), 'SaleController', 'addPayment');
    }

    public function test_negative_tender_on_payment_edit_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalInput::class)->validate(Request::create('/sales/updatepayment', 'POST', ['edit_amount' => 5, 'edit_paying_amount' => -1]), 'SaleController', 'updatePayment');
    }

    public function test_missing_line_price_is_rejected_before_saving(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalInput::class)->validate($this->sale(['net_unit_price' => [10, 15]]), 'SaleController', 'store');
    }

    public function test_unknown_product_cannot_create_a_sale_with_missing_lines(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalInput::class)->validate($this->sale(['product_id' => [999]]), 'SaleController', 'store');
    }

    public function test_overflowing_payment_value_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(OperationalInput::class)->validate(Request::create('/sales/add_payment', 'POST', ['amount' => '1e309']), 'SaleController', 'addPayment');
    }
}
