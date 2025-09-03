<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trailer extends Model
{
    protected $fillable = [
        'name', 
        'center_id',
        'status',   
        'capacity_kg', 
        'capacity_m3'
    ];
    public function center(){
        return $this->belongsTo(Center::class);
    }
    public function shipments() {
        return $this->hasMany(Shipment::class);
    }
}
