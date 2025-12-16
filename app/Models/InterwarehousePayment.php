<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterwarehousePayment extends Model
{
    use SoftDeletes;

    protected $table = 'interwarehouse_payments';
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'id',
        'Ref',
        'interwarehouse_request_id',
        'date',
        'montant',
        'payment_method_id',
        'account_id',
        'user_id',
        'notes',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'interwarehouse_request_id' => 'integer',
        'payment_method_id' => 'integer',
        'account_id' => 'integer',
        'user_id' => 'integer',
        'montant' => 'double',
    ];

    /**
     * Get the parent request.
     */
    public function request()
    {
        return $this->belongsTo('App\Models\InterwarehouseRequest', 'interwarehouse_request_id');
    }

    /**
     * Get the payment method.
     */
    public function paymentMethod()
    {
        return $this->belongsTo('App\Models\PaymentMethod', 'payment_method_id');
    }

    /**
     * Get the account.
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * Get the user who made the payment.
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
