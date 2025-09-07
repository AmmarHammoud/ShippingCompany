<?php

namespace App\Services;

use App\Events\ShipmentHandedToCenter;
use App\Events\ShipmentOfferResponded;
use App\Models\Shipment;
use App\Models\ShipmentDriverOffer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\OfferShipmentToNearestDriverService;
use Illuminate\Validation\ValidationException;

class ShipmentDriverOfferService
{
    /**
     */
    public static function accept(int $shipmentId, string $stage = 'pickup'): Shipment
    {
        $driver = Auth::user();

        $offer = ShipmentDriverOffer::where('shipment_id', $shipmentId)
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->where('stage', $stage)
            ->firstOrFail();

        $shipment = Shipment::findOrFail($shipmentId);

        if ($stage === 'pickup') {
            if ($shipment->pickup_driver_id) {
                throw new \Exception('Pickup already assigned.');
            }

            $shipment->update([
                'pickup_driver_id' => $driver->id, // QR confirmation required
            ]);
        }

        if ($stage === 'delivery') {
            if ($shipment->delivery_driver_id) {
                throw new \Exception('Delivery already assigned.');
            }

            $shipment->update([
                'delivery_driver_id' => $driver->id,
                'status' => 'out_for_delivery',
            ]);
        }

        $offer->update(['status' => 'accepted']);
        event(new ShipmentOfferResponded($shipment, 'accepted'));

        ShipmentDriverOffer::where('shipment_id', $shipmentId)
            ->where('driver_id', '!=', $driver->id)
            ->where('stage', $stage)
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
        event(new ShipmentOfferResponded($shipment, 'rejected'));

        if (is_null($shipment->pickup_driver_id)) {
            return OfferShipmentToNearestDriverService::offer($shipment, $offer->stage);
        }

        return null;
    }


    public static function confirmPickupByBarcode(string $barcode, User $driver): Shipment
    {
        $shipment = Shipment::where('barcode', $barcode)->first();

        if (! $shipment) {
            throw ValidationException::withMessages(['barcode' => ['Shipment not found.']]);
        }

        if ($shipment->pickup_driver_id !== $driver->id) {
            throw ValidationException::withMessages(['driver' => ['Unauthorized driver for this shipment.']]);
        }

        if ($shipment->status !== 'offered_pickup_driver') {
            throw ValidationException::withMessages(['status' => ['Shipment is not ready for pickup confirmation.']]);
        }

        $shipment->update([
            'status' => 'picked_up',
        ]);

        return $shipment;
    }




    public static function confirmHandOverToCenter(Shipment $shipment, User $driver)
    {
        if ($shipment->pickup_driver_id !== $driver->id) {
            throw ValidationException::withMessages(['driver' => ['Unauthorized driver for this shipment.']]);
        }

        if ($shipment->status !== 'picked_up') {
            throw ValidationException::withMessages(['status' => ['Shipment status does not allow handover.']]);
        }

        $shipment->status = 'pending_at_center';
        $shipment->save();

        return $shipment;
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
