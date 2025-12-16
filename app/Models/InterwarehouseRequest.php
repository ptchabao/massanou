<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterwarehouseRequest extends Model
{
    use SoftDeletes;

    protected $table = 'interwarehouse_requests';
    protected $dates = ['deleted_at', 'proforma_at', 'validated_at'];

    protected $fillable = [
        'id',
        'Ref',
        'date',
        'requester_warehouse_id',
        'supplier_warehouse_id',
        'user_id',
        'statut',
        'items',
        'tax_rate',
        'TaxNet',
        'discount',
        'discount_type',
        'shipping',
        'GrandTotal',
        'paid_amount',
        'payment_threshold',
        'desired_date',
        'notes',
        'proforma_notes',
        'proforma_by',
        'proforma_at',
        'validated_by',
        'validated_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'requester_warehouse_id' => 'integer',
        'supplier_warehouse_id' => 'integer',
        'user_id' => 'integer',
        'proforma_by' => 'integer',
        'validated_by' => 'integer',
        'items' => 'double',
        'tax_rate' => 'double',
        'TaxNet' => 'double',
        'discount' => 'double',
        'shipping' => 'double',
        'GrandTotal' => 'double',
        'paid_amount' => 'double',
        'payment_threshold' => 'double',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_PROFORMA_SENT = 'proforma_sent';
    const STATUS_REJECTED = 'rejected';
    const STATUS_VALIDATED = 'validated';
    const STATUS_IN_PAYMENT = 'in_payment';
    const STATUS_PAID = 'paid';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CLOSED = 'closed';

    /**
     * Get the user who created the request.
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    /**
     * Get the requester warehouse.
     */
    public function requesterWarehouse()
    {
        return $this->belongsTo('App\Models\Warehouse', 'requester_warehouse_id');
    }

    /**
     * Get the supplier warehouse.
     */
    public function supplierWarehouse()
    {
        return $this->belongsTo('App\Models\Warehouse', 'supplier_warehouse_id');
    }

    /**
     * Get the user who created the proforma.
     */
    public function proformaUser()
    {
        return $this->belongsTo('App\Models\User', 'proforma_by');
    }

    /**
     * Get the user who validated the request.
     */
    public function validatedUser()
    {
        return $this->belongsTo('App\Models\User', 'validated_by');
    }

    /**
     * Get the request details.
     */
    public function details()
    {
        return $this->hasMany('App\Models\InterwarehouseItem', 'interwarehouse_request_id');
    }

    /**
     * Get the payments.
     */
    public function payments()
    {
        return $this->hasMany('App\Models\InterwarehousePayment', 'interwarehouse_request_id');
    }

    /**
     * Get the deliveries.
     */
    public function deliveries()
    {
        return $this->hasMany('App\Models\InterwarehouseDelivery', 'interwarehouse_request_id');
    }

    /**
     * Calculate remaining amount to pay.
     */
    public function getRemainingAmountAttribute()
    {
        return $this->GrandTotal - $this->paid_amount;
    }

    /**
     * Calculate payment percentage.
     */
    public function getPaymentPercentageAttribute()
    {
        if ($this->GrandTotal <= 0) {
            return 100;
        }
        return round(($this->paid_amount / $this->GrandTotal) * 100, 2);
    }

    /**
     * Check if delivery is allowed based on payment threshold.
     */
    public function canDeliver()
    {
        return $this->payment_percentage >= $this->payment_threshold;
    }
}
