<?php

namespace App\Services;

use App\Models\Center;

class NearestCenterService
{
    
    public static function getNearestCenter(float $lat, float $lng): ?Center
    {
        return Center::selectRaw("
            id, name, latitude, longitude,
            (6371 * acos(
                cos(radians(?)) *
                cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(latitude))
            )) AS distance
        ", [$lat, $lng, $lat])
            ->orderBy('distance')
            ->first();
    }
}
