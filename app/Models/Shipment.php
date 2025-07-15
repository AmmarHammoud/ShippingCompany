<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'driver_id',
        'sender_lat',
        'sender_lng',
        'recipient_name',
        'recipient_phone',
        'recipient_location',
        'recipient_lat',
        'recipient_lng',
        'shipment_type',
        'number_of_pieces',
        'weight',
        'delivery_price',
        'total_amount',
        'invoice_number',
        'barcode',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
    public function offers()
    {
        return $this->hasMany(ShipmentDriverOffer::class);
    }

}
