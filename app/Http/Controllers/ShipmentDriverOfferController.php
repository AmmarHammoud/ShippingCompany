<?php

namespace App\Http\Controllers;

use App\Http\Requests\HandleShipmentOfferRequest;
use App\Models\Shipment;
use App\Models\ShipmentDriverOffer;
use App\Services\ShipmentDriverOfferService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\AmmarResource;
use App\Http\Resources\OffersResource;

class ShipmentDriverOfferController extends Controller
{

    public function acceptOffer(int $shipmentId): JsonResponse
{
        try {
            $shipment = ShipmentDriverOfferService::accept((int) $shipmentId);

            return response()->json([
                'message' => 'Shipment assigned successfully.',
                'shipment' => new AmmarResource($shipment),
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
          $driver = Auth::user();
        try {
            $shipment = Shipment::find($shipmentId);

            if (! $shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment not found.',
                ], 404);
            }

            if ($shipment->pickup_driver_id !== $driver->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to access this shipment.',
                ], 403);
            }

            $updatedShipment = ShipmentDriverOfferService::confirmHandOverToCenter($shipment, $driver);

            return response()->json([
                'message' => 'Shipment handed over to center successfully.',
                'shipment' => $updatedShipment,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }}

    public function offersByStatus(Request $request): JsonResponse
    {
        $status = $request->query('status');

        if (! in_array($status, ['pending', 'accepted', 'rejected'])) {
            return response()->json(['error' => 'Invalid status.'], 422);
        }

        $offers = ShipmentDriverOfferService::getOffersByStatus($status);

        return response()->json([
            'offers' => OffersResource::collection($offers),
        ]);
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
            'shipments' => new AmmarResource($shipment),
        ]);
    }
}
