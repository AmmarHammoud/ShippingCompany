<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Center extends Model
{

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function drivers() {
        return $this->hasMany(User::class)->where('role', 'driver');
    }
    public function manager()
    {
        return $this->hasOne(User::class, 'center_id')->where('role', 'center_manager');
    }
}
