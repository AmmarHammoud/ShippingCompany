<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Models\ShipmentDriverOffer;

class OfferShipmentToNearestDriverService
{

    public static function offerToNearestDriver(Shipment $shipment): ?User
    {
        $drivers = User::where('role', 'driver')
            ->where('is_approved', true)
            ->where('active', true)
            ->where('center_id', $shipment->client->center_id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        // ترتيب السائقين حسب المسافة إلى المرسل
        $sortedDrivers = $drivers->sortBy(function ($driver) use ($shipment) {
            return self::calculateDistance(
                $shipment->sender_lat,
                $shipment->sender_lng,
                $driver->latitude,
                $driver->longitude
            );
        });

        foreach ($sortedDrivers as $driver) {
            $alreadyOffered = ShipmentDriverOffer::where('shipment_id', $shipment->id)
                ->where('driver_id', $driver->id)
                ->exists();

            if (! $alreadyOffered) {
                ShipmentDriverOffer::create([
                    'shipment_id' => $shipment->id,
                    'driver_id'   => $driver->id,
                    'status'      => 'pending',
                ]);

                return $driver;
            }
        }

        return null;

    }
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371; // نصف قطر الأرض بالكيلومتر
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
