<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentDriverOffer extends Model
{
    protected $fillable = ['shipment_id', 'driver_id', 'status','stage'];

    public function shipment()
    {
        return $this->belongsTo(shipment::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
