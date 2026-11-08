<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryProduct extends Model
{
    protected $fillable = [
        'delivery_id',
        'product_sale_id',
        'product_id',
        'qty',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function productSale()
    {
        return $this->belongsTo(Product_Sale::class, 'product_sale_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
