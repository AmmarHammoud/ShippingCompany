<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'center_from_id',
        'center_to_id',
        'pickup_driver_id',
        'delivery_driver_id',
        'sender_lat',
        'sender_lng',
        'recipient_id',
        'recipient_location',
        'recipient_lat',
        'recipient_lng',
        'shipment_type',
        'number_of_pieces',
        'weight',
        'delivery_price',
        'product_value',
        'total_amount',
        'invoice_number',
        'barcode',
        'status',
        'qr_code_url',
        'delivered_at'
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
    public function centerFrom()
    {
        return $this->belongsTo(Center::class, 'center_from_id');
    }

    public function centerTo()
    {
        return $this->belongsTo(Center::class, 'center_to_id');
    }
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
    public function rating()
    {
        return $this->hasOne(Rating::class);
    }
    public function trailer() 
    {
        return $this->belongsTo(Trailer::class);
    }
    public function expense(){
        return $this->hasOne(Expense::class);

    }
 
}
