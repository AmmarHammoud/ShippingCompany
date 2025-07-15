<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentDriverOffer;
use Illuminate\Support\Facades\Auth;
use App\Services\OfferShipmentToNearestDriverService;

class ShipmentDriverOfferService
{
    /**
     */
    public static function accept(int $shipmentId): Shipment
    {
        $driver = Auth::user();

        $offer = ShipmentDriverOffer::where('shipment_id', $shipmentId)
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $shipment = Shipment::findOrFail($shipmentId);

        if ($shipment->driver_id !== null) {
            throw new \Exception('Shipment already assigned.');
        }

        $shipment->update([
            'driver_id' => $driver->id,
            'status' => 'assigned',
        ]);

        $offer->update(['status' => 'accepted']);

        ShipmentDriverOffer::where('shipment_id', $shipmentId)
            ->where('driver_id', '!=', $driver->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return $shipment;
    }

    public static function reject(int $shipmentId): ?object
    {
        $driver = Auth::user();

        $offer = ShipmentDriverOffer::where('shipment_id', $shipmentId)
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $offer->update(['status' => 'rejected']);

        $shipment = Shipment::findOrFail($shipmentId);

        if (is_null($shipment->driver_id)) {
            return OfferShipmentToNearestDriverService::offerToNearestDriver($shipment);
        }

        return null;
    }
    public static function getOffersByStatus(string $status)
    {
        $driver = Auth::user();

        return ShipmentDriverOffer::with('shipment')
            ->where('driver_id', $driver->id)
            ->where('status', $status)
            ->latest()
            ->get();
    }
}
