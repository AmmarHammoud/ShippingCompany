<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'shipment_id', 
        'description', 
        'amout'
    ];
    public function shipment() {
        return $this->belongsTo(Shipment::class);
    }
}
