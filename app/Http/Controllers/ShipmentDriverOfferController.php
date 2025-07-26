<?php

namespace App\Http\Controllers;

use App\Http\Requests\HandleShipmentOfferRequest;
use App\Models\Shipment;
use App\Models\ShipmentDriverOffer;
use App\Services\ShipmentDriverOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    public function confirmPickupByDriver($barcode)
    {
        $driver = Auth::user();

        if (! $driver) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $shipment = ShipmentDriverOfferService::confirmPickupByBarcode($barcode, $driver);

            return response()->json([
                'message' => 'Shipment pickup confirmed by driver.',
                'shipment_id' => $shipment->id,
                'status' => $shipment->status,

            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Internal server error',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    public function confirmHandOverToCenter(Request $request, $shipmentId)
    {
        $shipment = ShipmentDriverOffer::findOrFail($shipmentId);
        $driver = Auth::user();

        try {
            $updatedShipment = ShipmentDriverOfferService::confirmHandOverToCenter($shipment, $driver);

            return response()->json([
                'message' => 'Shipment handed over to center successfully.',
                'shipment' => $updatedShipment,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }




    public function offersByStatus(Request $request): JsonResponse
    {
        $status = $request->query('status');

        if (! in_array($status, ['pending', 'accepted', 'rejected'])) {
            return response()->json(['error' => 'Invalid status.'], 422);
        }

        $offers = ShipmentDriverOfferService::getOffersByStatus($status);

        return response()->json(['offers' => $offers]);
    }
    public function myShipments(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'client') {
            return response()->json(['error' => 'Access denied. Only clients can view their shipments.'], 403);
        }

        $status = $request->query('status');

        $query = Shipment::with(['centerFrom', 'centerTo', 'pickupDriver', 'deliveryDriver'])
            ->where('client_id', $user->id);

        if ($status) {
            $validStatuses = [
                'pending',
                'offered_pickup_driver',
                'picked_up',
                'in_transit_between_centers',
                'arrived_at_destination_center',
                'offered_delivery_driver',
                'out_for_delivery',
                'delivered',
                'cancelled',
            ];

            if (!in_array($status, $validStatuses)) {
                return response()->json(['error' => 'Invalid status provided.'], 422);
            }

            $query->where('status', $status);
        }

        $shipments = $query->latest()->get();

        return response()->json([
            'shipments' => $shipments,
        ]);
    }
}
