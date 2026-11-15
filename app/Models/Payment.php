<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public bool $skipCustomerCreditAllocation = false;

    public function save(array $options = [])
    {
        if ($this->paying_method === 'Deposit') {
            return \Illuminate\Support\Facades\DB::transaction(fn () => parent::save($options));
        }
        return parent::save($options);
    }

    protected static function booted(): void
    {
        static::observe(\App\Observers\CustomerCreditPaymentObserver::class);
    }

    protected $fillable =[
        "purchase_id", "user_id", "sale_id", "cash_register_id", "account_id","payment_receiver", "payment_reference", "amount", "used_points", "change", "paying_method","payment_proof", "payment_note"
    ];
}
