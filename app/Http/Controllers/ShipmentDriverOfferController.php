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

class ShipmentDriverOfferController extends Controller
{

    public function acceptOffer(int $shipmentId): JsonResponse
    {
        try {
            $shipment = ShipmentDriverOfferService::accept((int) $shipmentId);

            // جيب آخر عرض لنفس السائق الحالي
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
                'data' => $formatted
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
    });

    return response()->json([
        'offers' => $formatted
    ]);
}
}
