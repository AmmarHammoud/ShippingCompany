<?php

namespace App\Http\Controllers;

use App\Http\Requests\HandleShipmentOfferRequest;
use App\Services\ShipmentDriverOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentDriverOfferController extends Controller
{
    
    public function acceptOffer(int $shipmentId): JsonResponse
{
        try {
            $shipment = ShipmentDriverOfferService::accept((int) $shipmentId);

            return response()->json([
                'message' => 'Shipment assigned successfully.',
                'shipment' => $shipment
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }


    public function rejectOffer(int $shipmentId): JsonResponse

    {
        try {
            $nextDriver = ShipmentDriverOfferService::reject((int) $shipmentId);

            return response()->json([
                'message' => $nextDriver
                    ? 'Offer rejected. Shipment offered to next driver.'
                    : 'Offer rejected. No other drivers available.',
                'next_driver_id' => $nextDriver?->id
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }
    public function offersByStatus(Request $request    ): JsonResponse
    {
        $status = $request->query('status');

        if (! in_array($status, ['pending', 'accepted', 'rejected'])) {
            return response()->json(['error' => 'Invalid status.'], 422);
        }

        $offers = ShipmentDriverOfferService::getOffersByStatus($status);

        return response()->json(['offers' => $offers]);
    }
}
