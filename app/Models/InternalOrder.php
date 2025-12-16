<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalOrder extends Model
{
    protected $table = 'internal_orders';
    protected $dates = ['deleted_at', 'approved_at'];

    protected $fillable = [
        'id',
        'date',
        'user_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'items',
        'statut',
        'notes',
        'GrandTotal',
        'discount',
        'discount_type',
        'shipping',
        'TaxNet',
        'tax_rate',
        'approved_by',
        'approved_at',
        'transfer_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'Ref'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'from_warehouse_id' => 'integer',
        'to_warehouse_id' => 'integer',
        'approved_by' => 'integer',
        'transfer_id' => 'integer',
        'items' => 'double',
        'GrandTotal' => 'double',
        'discount' => 'double',
        'shipping' => 'double',
        'TaxNet' => 'double',
        'tax_rate' => 'double',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function details()
    {
        return $this->hasMany('App\Models\InternalOrderDetail');
    }

    public function fromWarehouse()
    {
        return $this->belongsTo('App\Models\Warehouse', 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo('App\Models\Warehouse', 'to_warehouse_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo('App\Models\User', 'approved_by');
    }

    public function transfer()
    {
        return $this->belongsTo('App\Models\Transfer', 'transfer_id');
    }

}
