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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ShipmentDriverOfferController extends Controller
{
    /**
     * قبول عرض لسائق. يدعم المرحلتين:
     * - ?stage=pickup (الافتراضي) أو delivery
     * - إذا لم تُمرَّر stage، نكتشفها تلقائيًا من آخر عرض pending لنفس السائق والشحنة.
     */
    public function acceptOffer(Request $request, int $shipmentId): JsonResponse
    {
        try {
                $stageParam = $request->query('stage'); // optional: pickup|delivery
            if ($stageParam !== null && !in_array($stageParam, ['pickup','delivery'])) {
                return response()->json(['error' => 'Invalid stage. Allowed: pickup, delivery'], 422);
            }

            // اكتشاف تلقائي للمرحلة إن لم تُمرّر
            if ($stageParam === null) {
                $pendingOffer = ShipmentDriverOffer::where('shipment_id', $shipmentId)
                    ->where('driver_id', Auth::id())
                    ->where('status', 'pending')
                    ->latest()
                    ->first();
                $stageParam = $pendingOffer?->stage ?? 'pickup';
            }

            $shipment = ShipmentDriverOfferService::accept((int) $shipmentId, $stageParam);

            // جلب أحدث عرض بعد التحديث (اختياري للتنسيق)
            $offer = ShipmentDriverOffer::where('shipment_id', $shipment->id)
                ->where('driver_id', Auth::id())
                ->latest()
                ->first();

            $formatted = [
                'offer' => $offer ? [
                    'id'         => $offer->id,
                    'status'     => $offer->status,
                    'stage'      => $offer->stage,
                    'created_at' => $offer->created_at,
                    'updated_at' => $offer->updated_at,
                ] : null,

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
                ],
            ];

            return response()->json([
                'message'  => 'Shipment assigned successfully.',
                'stage'    => $stageParam,
                'data'     => $formatted,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    /**
     * رفض عرض. الخدمة تتكفّل باكتشاف المرحلة من آخر عرض pending لنفس السائق.
     * (يمكن أيضًا تمرير ?stage=pickup|delivery اختياريًا إن أردت الالتزام الصريح)
     */
    public function rejectOffer(Request $request, int $shipmentId): JsonResponse
    {
        try {
            // اختياري: توجيه صريح لمرحلة معينة (لو استخدمت rejectForStage)
            // $stage = $request->query('stage'); // 'pickup'|'delivery' أو null
            // $nextDriver = $stage ? ShipmentDriverOfferService::rejectForStage((int)$shipmentId, $stage)
            //                      : ShipmentDriverOfferService::reject((int)$shipmentId);

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

    /**
     * تأكيد استلام السائق للشحنة من العميل (Pickup) عبر مسح الباركود.
     */
    public function confirmPickupByDriver(string $barcode): JsonResponse
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
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Internal server error',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * (جديد) تأكيد خروج الشحنة للتسليم (Delivery) عبر الباركود.
     * يدعم الانتقال من offered_delivery_driver إلى out_for_delivery (idempotent).
     */
    public function confirmOutForDeliveryByDriver(string $barcode): JsonResponse
    {
        $driver = Auth::user();

        if (! $driver) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $shipment = ShipmentDriverOfferService::confirmOutForDeliveryByBarcode($barcode, $driver);

            return response()->json([
                'message' => 'Confirmed: shipment is out for delivery.',
                'shipment_id' => $shipment->id,
                'status' => $shipment->status,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Internal server error',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تسليم الشحنة للمركز بعد الالتقاط.
     */
    public function confirmHandOverToCenter(Request $request, int $shipmentId): JsonResponse
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
                'message'  => 'Shipment handed over to center successfully.',
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
        }
    }

    /**
     * (جديد) تأكيد التسليم للمستلم. يدعم OTP/التوقيع/الموقع اختياريًا.
     * Barameters (JSON body):
     * - otp: string?      - signed_url: url?
     * - delivered_lat: numeric?   - delivered_lng: numeric?
     */
    public function confirmHandOverToRecipient(Request $request, int $shipmentId): JsonResponse
    {
        $driver = Auth::user();

        $request->validate([
            'otp'            => 'nullable|string|max:10',
            'signed_url'     => 'nullable|url',
            'delivered_lat'  => 'nullable|numeric',
            'delivered_lng'  => 'nullable|numeric',
        ]);

        try {
            $shipment = Shipment::findOrFail($shipmentId);

            $updated = ShipmentDriverOfferService::confirmHandOverToRecipient(
                $shipment,
                $driver,
                $request->input('otp'),
                $request->only(['signed_url','delivered_lat','delivered_lng'])
            );

            return response()->json([
                'message'  => 'Shipment delivered successfully.',
                'shipment' => $updated,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * جلب العروض حسب الحالة، مع دعم stage اختياريًا (pickup|delivery).
     * GET /offers?status=pending&stage=delivery
     */
    public function offersByStatus(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $stage  = $request->query('stage'); // optional

        if (! in_array($status, ['pending', 'accepted', 'rejected'])) {
            return response()->json(['error' => 'Invalid status.'], 422);
        }
        if ($stage !== null && ! in_array($stage, ['pickup','delivery'])) {
            return response()->json(['error' => 'Invalid stage.'], 422);
        }

        $offers = ShipmentDriverOfferService::getOffersByStatus($status, $stage);

        $formatted = $offers->map(function ($offer) {
            $shipment = $offer->shipment;

            return [
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

        return response()->json(['offers' => $formatted]);
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
                'pending_at_center',
                'arrived_at_center',
                'assigned_to_trailer',
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

        return response()->json(['shipments' => $shipments]);
    }

    public function updateDriver(Request $request, $driverId = null)
    {
        $driverId = $driverId ?? Auth::id();

        $rules = [
            'name'        => 'sometimes|string|max:255',
            'email'       => 'sometimes|email|unique:users,email,' . $driverId,
            'phone'       => 'sometimes|string|unique:users,phone,' . $driverId,
            'password'    => 'sometimes|string|min:6',
            'center_id'   => 'sometimes|nullable|exists:centers,id',
            'latitude'    => 'sometimes|nullable|numeric',
            'longitude'   => 'sometimes|nullable|numeric',
            'is_approved' => 'sometimes|boolean',
            'active'      => 'sometimes|boolean',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $dataToUpdate = $request->only(array_keys($rules));

        if (isset($dataToUpdate['password'])) {
            $dataToUpdate['password'] = Hash::make($dataToUpdate['password']);
        }

        $user = User::findOrFail($driverId);

        $user->update([
            'name'        => $dataToUpdate['name'] ?? $user->name,
            'email'       => $dataToUpdate['email'] ?? $user->email,
            'phone'       => $dataToUpdate['phone'] ?? $user->phone,
            'password'    => $dataToUpdate['password'] ?? $user->password,
            'center_id'   => $dataToUpdate['center_id'] ?? $user->center_id,
            'is_approved' => $dataToUpdate['is_approved'] ?? $user->is_approved,
            'active'      => $dataToUpdate['active'] ?? $user->active,
            'latitude'    => $dataToUpdate['latitude'] ?? $user->latitude,
            'longitude'   => $dataToUpdate['longitude'] ?? $user->longitude,
        ]);

        return response()->json([
            'message' => 'Driver updated successfully.',
            'data'    => $user
        ]);
    }
}
