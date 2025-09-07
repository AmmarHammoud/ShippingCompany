<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trailer extends Model
{
    protected $fillable = [
        'name',
        'center_id',
        'center_to_id',
        'status',
        'capacity_kg',
        'capacity_m3'
    ];
    public function center(){
        return $this->belongsTo(Center::class);
    }

    public function centerTo() {
        return $this->belongsTo(Center::class, 'center_to_id');
    }

    public function shipments() {
        return $this->hasMany(Shipment::class);
    }
}
