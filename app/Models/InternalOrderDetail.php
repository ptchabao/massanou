<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalOrderDetail extends Model
{
    protected $table = 'internal_order_details';

    protected $fillable = [
        'id',
        'internal_order_id',
        'quantity',
        'purchase_unit_id',
        'product_id',
        'total',
        'product_variant_id',
        'cost',
        'TaxNet',
        'discount',
        'discount_method',
        'tax_method',
    ];

    protected $casts = [
        'total' => 'double',
        'cost' => 'double',
        'TaxNet' => 'double',
        'discount' => 'double',
        'quantity' => 'double',
        'internal_order_id' => 'integer',
        'purchase_unit_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
    ];

    public function internalOrder()
    {
        return $this->belongsTo('App\Models\InternalOrder');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }

    public function productVariant()
    {
        return $this->belongsTo('App\Models\ProductVariant');
    }

    public function unit()
    {
        return $this->belongsTo('App\Models\Unit', 'purchase_unit_id');
    }

}
