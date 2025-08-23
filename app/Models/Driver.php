<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'is_blocked'
    ];

    public function shipments() {
        return $this->hasMany(Shipment::class);
    }
    public function center() { 
        return $this->belongsTo(Center::class);
    }
}
