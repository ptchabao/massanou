<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterwarehouseItem extends Model
{
    protected $table = 'interwarehouse_items';

    protected $fillable = [
        'id',
        'interwarehouse_request_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'quantity',
        'cost',
        'TaxNet',
        'tax_method',
        'discount',
        'discount_method',
        'total',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'interwarehouse_request_id' => 'integer',
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'unit_id' => 'integer',
        'quantity' => 'double',
        'cost' => 'double',
        'TaxNet' => 'double',
        'discount' => 'double',
        'total' => 'double',
    ];

    /**
     * Get the parent request.
     */
    public function request()
    {
        return $this->belongsTo('App\Models\InterwarehouseRequest', 'interwarehouse_request_id');
    }

    /**
     * Get the product.
     */
    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }

    /**
     * Get the product variant.
     */
    public function productVariant()
    {
        return $this->belongsTo('App\Models\ProductVariant', 'product_variant_id');
    }

    /**
     * Get the unit.
     */
    public function unit()
    {
        return $this->belongsTo('App\Models\Unit');
    }
}
