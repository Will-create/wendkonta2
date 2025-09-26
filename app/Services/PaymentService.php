<?php

namespace App\Services;

use Stripe\Stripe;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PosSetting;
use App\Payment\SslCommerz;
use App\Payment\BkashPayment;
use App\Payment\PaypalPayment;
use App\Payment\StripePayment;
use App\Payment\PaydunyaPayment;
use App\Payment\PaystackPayment;
use App\Payment\RazorpayPayment;
use App\Models\PaymentWithCheque;
use Illuminate\Support\Facades\Auth;
use App\Models\PaymentWithCreditCard;

class PaymentService
{
    public function initialize($payment_type)
    {
        switch ($payment_type) {
            case 'stripe':
                return new StripePayment();
            case 'paypal':
                return new PaypalPayment();
            case 'razorpay':
                return new RazorpayPayment();
            case 'paystack':
                return new PaystackPayment();
            case 'paydunya':
                return new PaydunyaPayment();
            case 'bkash':
                return new BkashPayment();
            case 'ssl_commerz':
                return new SslCommerz();
            default:
                break;
        }
    }

    public function payForPurchase(array $data) {
        $lims_purchase_data = Purchase::find($data['purchase_id']);
        $lims_purchase_data->paid_amount += $data['amount'];
        $balance = $lims_purchase_data->grand_total - $lims_purchase_data->paid_amount;
        // dd($data, $balance, $lims_purchase_data);
        if($balance > 0)
            $lims_purchase_data->payment_status = 1;
        else
            $lims_purchase_data->payment_status = 2;

        $lims_purchase_data->save();

        if($data['paid_by_id'] == 1)
            $paying_method = 'Cash';
        elseif ($data['paid_by_id'] == 2)
            $paying_method = 'Gift Card';
        elseif ($data['paid_by_id'] == 3)
            $paying_method = 'Credit Card';
        else
            $paying_method = 'Cheque';

        $lims_payment_data = new Payment();
        $lims_payment_data->user_id = Auth::id();
        $lims_payment_data->purchase_id = $lims_purchase_data->id;
        $lims_payment_data->account_id = $data['account_id'];
        $lims_payment_data->payment_reference = 'ppr-' . date("Ymd") . '-'. date("his");
        $lims_payment_data->amount = $data['amount'];
        $lims_payment_data->change = $data['paying_amount'] - $data['amount'];
        $lims_payment_data->paying_method = $paying_method;
        $lims_payment_data->payment_note = $data['payment_note'];
        $lims_payment_data->save();

        $lims_payment_data = Payment::latest()->first();
        $data['payment_id'] = $lims_payment_data->id;
        $lims_pos_setting_data = PosSetting::latest()->first();
        if($paying_method == 'Credit Card' && $lims_pos_setting_data->stripe_secret_key){
            Stripe::setApiKey($lims_pos_setting_data->stripe_secret_key);
            $token = $data['stripeToken'];
            $amount = $data['amount'];

            // Charge the Customer
            $charge = \Stripe\Charge::create([
                'amount' => $amount * 100,
                'currency' => 'usd',
                'source' => $token,
            ]);

            $data['charge_id'] = $charge->id;
            PaymentWithCreditCard::create($data);
        }
        elseif ($paying_method == 'Cheque') {
            PaymentWithCheque::create($data);
        }

        return [
            'status' => true,
            'message' => 'Payment created successfully',
            'data' => '',
        ];
    }
}
