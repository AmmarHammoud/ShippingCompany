<?php

namespace App\Services;

use App\Events\ShipmentOfferedToDriver;
use App\Models\Shipment;
use App\Models\User;
use App\Models\ShipmentDriverOffer;

class OfferShipmentToNearestDriverService
{



    public static function offer(Shipment $shipment, string $stage = 'pickup'): ?User
    {
        $centerField = $stage === 'pickup' ? 'center_from_id' : 'center_to_id';
        $driverField = $stage === 'pickup' ? 'pickup_driver_id' : 'delivery_driver_id';

        if ($shipment->{$driverField}) {
            return null;
        }

        $drivers = User::where('role', 'driver')
            ->where('is_approved', true)
            ->where('active', true)
            ->where('center_id', $shipment->{$centerField})
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $sourceLat = $stage === 'pickup' ? $shipment->sender_lat : $shipment->centerTo?->latitude;
        $sourceLng = $stage === 'pickup' ? $shipment->sender_lng : $shipment->centerTo?->longitude;

        $sortedDrivers = $drivers->sortBy(function ($driver) use ($sourceLat, $sourceLng) {
            return self::calculateDistance(
                $sourceLat,
                $sourceLng,
                $driver->latitude,
                $driver->longitude
            );
        });

        foreach ($sortedDrivers as $driver) {
            $alreadyOffered = ShipmentDriverOffer::where('shipment_id', $shipment->id)
                ->where('driver_id', $driver->id)
                ->where('stage', $stage)
                ->exists();

            if (! $alreadyOffered) {
                ShipmentDriverOffer::create([
                    'shipment_id' => $shipment->id,
                    'driver_id'   => $driver->id,
                    'stage'       => $stage,
                    'status'      => 'pending',
                ]);
                event(new ShipmentOfferedToDriver($shipment, $driver->id));

                return $driver;
            }
        }

        return null;
    }

    /**
     * دالة حساب المسافة بين نقطتين (خطوط الطول والعرض)
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371; // بالكيلومتر
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
