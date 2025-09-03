<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'shipment_id',
        'stripe_session_id',
        'stripe_payment_id',
        'amount',
        'currency',
        'status'
    ];
}
