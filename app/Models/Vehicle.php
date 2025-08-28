<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_capacity_kg',
        'vehicle_capacity_m3'
    ];
}
