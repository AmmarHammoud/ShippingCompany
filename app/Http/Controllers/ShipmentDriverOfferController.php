<?php

namespace App\Http\Controllers;

use App\Http\Requests\HandleShipmentOfferRequest;
use App\Models\Shipment;
use App\Models\ShipmentDriverOffer;
use App\Models\User;
use App\Services\BatchDistributionService;
use App\Services\ShipmentClusteringService;
use App\Services\ShipmentDriverOfferService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\AmmarResource;
use App\Http\Resources\OffersResource;

class ShipmentDriverOfferController extends Controller
{

    public function acceptOffer(int $shipmentId): JsonResponse
    {
        try {
            $shipment = ShipmentDriverOfferService::accept((int) $shipmentId);



            $offer = \App\Models\ShipmentDriverOffer::where('shipment_id', $shipment->id)
                ->where('driver_id', Auth::id())
                ->latest()
                ->first();

            $formatted = [
                'offer' => [
                    'id'         => $offer->id,
                    'status'     => $offer->status,
                    'stage'      => $offer->stage,
                    'created_at' => $offer->created_at,
                    'updated_at' => $offer->updated_at,
                ],

                'shipment' => [
                    'id' => $shipment->id,
                    'invoice_number' => $shipment->invoice_number,
                    'barcode' => $shipment->barcode,
                    'status' => $shipment->status,
                    'shipment_type' => $shipment->shipment_type,
                    'number_of_pieces' => $shipment->number_of_pieces,
                    'weight' => $shipment->weight,
                    'delivery_price' => $shipment->delivery_price,
                    'product_value' => $shipment->product_value,
                    'total_amount' => $shipment->total_amount,
                    'qr_code_url' => $shipment->qr_code_url,
                    'delivered_at' => $shipment->delivered_at,
                    'created_at' => $shipment->created_at,
                    'updated_at' => $shipment->updated_at,],


                    'sender' => [
                        'id' => $shipment->client?->id,
                        'name' => $shipment->client?->name,
                        'email' => $shipment->client?->email,
                        'phone' => $shipment->client?->phone,
                        'lat' => $shipment->sender_lat,
                        'lng' => $shipment->sender_lng,
                    ],

                    'recipient' => [
                        'id' => $shipment->recipient?->id,
                        'name' => $shipment->recipient?->name,
                        'email' => $shipment->recipient?->email,
                        'phone' => $shipment->recipient?->phone,
                        'location' => $shipment->recipient_location,
                        'lat' => $shipment->recipient_lat,
                        'lng' => $shipment->recipient_lng,
                    ],
                ];


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

        $formatted = $offers->map(function ($offer) {
            $shipment = $offer->shipment;

            return [
                // بيانات العرض (Offer)
                'offer' => [
                    'id'         => $offer->id,
                    'status'     => $offer->status,
                    'stage'      => $offer->stage,
                    'created_at' => $offer->created_at,
                    'updated_at' => $offer->updated_at,
                ],

                // بيانات الشحنة (Shipment)
                'shipment' => [
                    'id' => $shipment->id,
                    'invoice_number' => $shipment->invoice_number,
                    'barcode' => $shipment->barcode,
                    'status' => $shipment->status,
                    'shipment_type' => $shipment->shipment_type,
                    'number_of_pieces' => $shipment->number_of_pieces,
                    'weight' => $shipment->weight,
                    'delivery_price' => $shipment->delivery_price,
                    'product_value' => $shipment->product_value,
                    'total_amount' => $shipment->total_amount,
                    'qr_code_url' => $shipment->qr_code_url,
                    'delivered_at' => $shipment->delivered_at,
                    'created_at' => $shipment->created_at,
                    'updated_at' => $shipment->updated_at,

                    'sender' => [
                        'id' => $shipment->client?->id,
                        'name' => $shipment->client?->name,
                        'email' => $shipment->client?->email,
                        'phone' => $shipment->client?->phone,
                        'lat' => $shipment->sender_lat,
                        'lng' => $shipment->sender_lng,
                    ],

                    'recipient' => [
                        'id' => $shipment->recipient?->id,
                        'name' => $shipment->recipient?->name,
                        'email' => $shipment->recipient?->email,
                        'phone' => $shipment->recipient?->phone,
                        'location' => $shipment->recipient_location,
                        'lat' => $shipment->recipient_lat,
                        'lng' => $shipment->recipient_lng,
                    ],
                ]
            ];
        });

        return response()->json([
            'offers' => $formatted
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
                'arrived_at_center',
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

    public function distributeArrivedShipments(Request $request): JsonResponse
    {
        $request->validate([
            'center_id' => 'required|exists:centers,id',
            'strategy' => 'in:clustering,capacity|required',
            'max_capacity' => 'required_if:strategy,capacity|numeric|min:0'
        ]);

        try {
            // الحصول على الشحنات التي وصلت إلى المركز المستهدف
            $shipments = Shipment::where('status', 'arrived_at_destination_center')
                ->where('center_to_id', $request->center_id)
                ->get();

            if ($shipments->isEmpty()) {
                return response()->json([
                    'message' => 'No shipments available for distribution'
                ], 404);
            }

            $clusteringService = new ShipmentClusteringService();
            $distributionService = new BatchDistributionService($clusteringService);

            if ($request->strategy === 'capacity' && $request->max_capacity) {
                // تجميع بناءً على سعة المركبات
                $clusters = $clusteringService->clusterShipmentsWithCapacity(
                    $shipments,
                    $request->max_capacity
                );

                // توزيع الدفعات
                $results = [];
                foreach ($clusters as $cluster) {
                    $centroid = $distributionService->calculateClusterCentroid($cluster);
                    $nearestDriver = $distributionService->findNearestDriver(
                        $centroid['lat'],
                        $centroid['lng'],
                        User::where('role', 'driver')
                            ->where('center_id', $request->center_id)
                            ->where('status', 'available')
                            ->get()
                    );

                    if ($nearestDriver) {
                        $results[] = $distributionService->offerBatchToDriver(
                            $cluster,
                            $nearestDriver,
                            'delivery'
                        );
                    }
                }
            } else {
                // التجميع الجغرافي العادي
                $results = $distributionService->distributeShipmentsToDrivers(
                    $shipments,
                    $request->center_id
                );
            }

            return response()->json([
                'message' => 'Shipments distributed successfully',
                'total_shipments' => $shipments->count(),
                'batches_created' => count($results),
                'batches' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Distribution failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * قبول دفعة كاملة من الشحنات
     */
    public function acceptBatch(Request $request): JsonResponse
    {
        $request->validate([
            'shipment_ids' => 'required|array',
            'shipment_ids.*' => 'exists:shipments,id'
        ]);

        $driver = Auth::user();
        $acceptedShipments = [];

        DB::beginTransaction();
        try {
            foreach ($request->shipment_ids as $shipmentId) {
                $shipment = Shipment::findOrFail($shipmentId);

                // التحقق من أن العرض لا يزال pending
                $offer = ShipmentDriverOffer::where('shipment_id', $shipmentId)
                    ->where('driver_id', $driver->id)
                    ->where('stage', 'delivery')
                    ->where('status', 'pending')
                    ->firstOrFail();

                // تحديث حالة الشحنة
                $shipment->update([
                    'delivery_driver_id' => $driver->id,
                    'status' => 'out_for_delivery',
                ]);

                // تحديث حالة العرض
                $offer->update(['status' => 'accepted']);

                $acceptedShipments[] = $shipment;
            }

            DB::commit();

            return response()->json([
                'message' => 'Batch accepted successfully',
                'accepted_shipments' => $acceptedShipments
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to accept batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * رفض دفعة كاملة من الشحنات
     */
    public function rejectBatch(Request $request): JsonResponse
    {
        $request->validate([
            'shipment_ids' => 'required|array',
            'shipment_ids.*' => 'exists:shipments,id'
        ]);

        $driver = Auth::user();

        try {
            foreach ($request->shipment_ids as $shipmentId) {
                $offer = ShipmentDriverOffer::where('shipment_id', $shipmentId)
                    ->where('driver_id', $driver->id)
                    ->where('stage', 'delivery')
                    ->where('status', 'pending')
                    ->firstOrFail();

                $offer->update(['status' => 'rejected']);
            }

            return response()->json([
                'message' => 'Batch rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to reject batch: ' . $e->getMessage()
            ], 500);
        }
    }
}

