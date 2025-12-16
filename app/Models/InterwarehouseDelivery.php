<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterwarehouseDelivery extends Model
{
    protected $table = 'interwarehouse_deliveries';

    protected $fillable = [
        'id',
        'Ref',
        'interwarehouse_request_id',
        'date',
        'user_id',
        'statut',
        'transfer_id',
        'notes',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'interwarehouse_request_id' => 'integer',
        'user_id' => 'integer',
        'transfer_id' => 'integer',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_RECEIVED = 'received';

    /**
     * Get the parent request.
     */
    public function request()
    {
        return $this->belongsTo('App\Models\InterwarehouseRequest', 'interwarehouse_request_id');
    }

    /**
     * Get the user who created the delivery.
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    /**
     * Get the related transfer.
     */
    public function transfer()
    {
        return $this->belongsTo('App\Models\Transfer');
    }
}
