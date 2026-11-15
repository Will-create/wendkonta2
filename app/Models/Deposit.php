<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $deposit) {
            if (!$deposit->getOriginal('receipt_token')) return;
            $allowed = ['reversed_at', 'reversed_by', 'reversal_note', 'reversal_cash_register_id', 'updated_at'];
            if (array_diff(array_keys($deposit->getDirty()), $allowed)
                || ($deposit->getOriginal('reversed_at') && $deposit->isDirty(array_diff($allowed, ['updated_at'])))) {
                abort(422, __('credit.immutable_receipt'));
            }
        });
        static::deleting(function (self $deposit) {
            if ($deposit->receipt_token) abort(422, __('credit.immutable_receipt'));
        });
    }

    protected $fillable =[
        "amount", "customer_id", "user_id", "note", "receipt_token", "request_hash",
        "transaction_date", "payment_method", "reference", "account_id", "cash_register_id"
    ];

    protected $casts = ['transaction_date' => 'datetime', 'reversed_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
