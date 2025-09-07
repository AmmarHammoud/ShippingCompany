<?php

namespace App\Http\Controllers;

use App\Events\ShipmentDeliveredBroadcast;
use App\Http\Requests\ShipmentUpdateRequest;
use App\Http\Requests\StoreRecipientRequest;
use App\Http\Requests\StoreShipmentDetailsRequest;
use App\Http\Requests\StoreShipmentRatingRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Models\Shipment;
use App\Services\ShipmentCreationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    protected ShipmentCreationService $shipmentRatingService;

    public function __construct(ShipmentCreationService $shipmentRatingService)
    {
        $this->shipmentRatingService = $shipmentRatingService;
    }
    public function storeRecipient(StoreRecipientRequest $request)
    {
        $recipientData = $request->validated();

        $cacheKey = 'recipient_data_' . Auth::id();
        Cache::put($cacheKey, $recipientData, now()->addMinutes(10));

        return response()->json([
            'message' => 'Recipient info saved. Proceed to shipment details.'
        ]);
    }
    public function storeDetails(StoreShipmentDetailsRequest $request)
    {
        $cacheKey = 'recipient_data_' . Auth::id();
        $recipientData = Cache::get($cacheKey);

        if (!$recipientData) {
            return response()->json(['error' => 'Recipient information not provided'], 422);
        }

        $shipment = ShipmentCreationService::create(
            $recipientData,
            $request->validated(),
            Auth::user()

        );


        $shipment->load(['recipient', 'client']);

        return response()->json([
            'message' => 'Shipment created successfully.',
            'shipment' => [
                'id' => $shipment->id,
                'barcode' => $shipment->barcode,
                'invoice_number' => $shipment->invoice_number,
                'status' => $shipment->status,
                'shipment_type' => $shipment->shipment_type,
                'number_of_pieces' => $shipment->number_of_pieces,
                'weight' => $shipment->weight,
                'delivery_price' => $shipment->delivery_price,
                'qr_code_url' => $shipment->qr_code_url,
                'created_at' => $shipment->created_at,
                'sender_location' => [
                    'lat' => $shipment->sender_lat,
                    'lng' => $shipment->sender_lng,
                ],
                'recipient_location' => [
                    'lat' => $shipment->recipient_lat,
                    'lng' => $shipment->recipient_lng,
                    'description' => $shipment->recipient_location,
                ],
                'recipient' => [
                    'id' => $shipment->recipient?->id,
                    'name' => $shipment->recipient?->name,
                    'phone' => $shipment->recipient?->phone,
                    'email' => $shipment->recipient?->email,
                ],
                'client' => [
                    'id' => $shipment->client?->id,
                    'name' => $shipment->client?->name,
                    'phone' => $shipment->client?->phone,
                    'email' => $shipment->client?->email,
                ],
            ],
            'confirmation_url' => url("/shipments/{$shipment->barcode}/confirm"),
            'driver_confirmation_url' => url("/shipments/{$shipment->barcode}/confirm-pickup")

        ]);
    }


    public function show(string $identifier)
    {
        try {
            $shipment = ShipmentCreationService::findByBarcodeOrId($identifier);

            return response()->json([
                'shipment' => [
                    'id' => $shipment->id,
                    'client_id' => $shipment->client_id,
                    'center_from_id' => $shipment->center_from_id,
                    'center_to_id' => $shipment->center_to_id,
                    'pickup_driver_id' => $shipment->pickup_driver_id,
                    'delivery_driver_id' => $shipment->delivery_driver_id,
                    'sender_lat' => $shipment->sender_lat,
                    'sender_lng' => $shipment->sender_lng,
                    'recipient_id' => $shipment->recipient_id,
                    'recipient_location' => $shipment->recipient_location,
                    'recipient_lat' => $shipment->recipient_lat,
                    'recipient_lng' => $shipment->recipient_lng,
                    'shipment_type' => $shipment->shipment_type,
                    'number_of_pieces' => $shipment->number_of_pieces,
                    'weight' => $shipment->weight,
                    'delivery_price' => $shipment->delivery_price,
                    'product_value' => $shipment->product_value,
                    'total_amount' => $shipment->total_amount,
                    'invoice_number' => $shipment->invoice_number,
                    'barcode' => $shipment->barcode,
                    'status' => $shipment->status,
                    'qr_code_url' => $shipment->qr_code_url,
                    'delivered_at' => $shipment->delivered_at,
                    'isPaid' =>$shipment->isPaid(),
                    'created_at' => $shipment->created_at,
                    'updated_at' => $shipment->updated_at,

                    // بيانات المرسل
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
                    ]
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Shipment not found.'], 404);
        }

}

    public function myShipments(Request $request)
    {
        $user = Auth::user();

        $shipments = ShipmentCreationService::getShipmentsByUser($user, $request->all());

        $data = $shipments->map(function ($shipment) {
            return [
                'id' => $shipment->id,
                'invoice_number' => $shipment->invoice_number,
                'barcode' => $shipment->barcode,
                'status' => $shipment->status,
                'qr_code_url' => $shipment->qr_code_url,
                'shipment_type' => $shipment->shipment_type,
                'number_of_pieces' => $shipment->number_of_pieces,
                'weight' => $shipment->weight,
                'product_value' => $shipment->product_value,
                'delivery_price' => $shipment->delivery_price,
                'total_amount' => $shipment->total_amount,
                'delivered_at' => $shipment->delivered_at,
                'created_at' => $shipment->created_at,
                'updated_at' => $shipment->updated_at,
                'sender' => [
                    'id' => $shipment->client_id,
                    'name' => optional($shipment->client)->name,
                    'lat' => $shipment->sender_lat,
                    'lng' => $shipment->sender_lng,
                ],
                'recipient' => [
                    'id' => $shipment->recipient_id,
                    'name' => optional($shipment->recipient)->name ?? '(غير معروف)',
                    'phone' => optional($shipment->recipient)->phone,
                    'location' => $shipment->recipient_location,
                    'lat' => $shipment->recipient_lat,
                    'lng' => $shipment->recipient_lng,
                ],
                'center_from' => [
                    'id' => $shipment->centerFrom?->id,
                    'name' => $shipment->centerFrom?->name,
                ],
                'center_to' => [
                    'id' => $shipment->centerTo?->id,
                    'name' => $shipment->centerTo?->name,
                ],
            ];
        });

        return response()->json(['shipments' => $data]);
    }

    public function confirmDelivery($barcode)
    {
        try {
            $shipment = ShipmentCreationService::confirmByBarcode($barcode);
            broadcast(new ShipmentDeliveredBroadcast($shipment));

            return response()->json([
                'message' => 'Shipment receipt confirmed',
                'shipment_id' => $shipment->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'shipment not found '], 404);
        }

    }
    public function update(ShipmentUpdateRequest $request, $id)
    {
        $shipment = Shipment::where('id', $id)
            ->where('client_id', Auth::id())
            ->first();

        if (! $shipment) {
            return response()->json([
                'message' => 'Shipment not found or does not belong to the authenticated user.'
            ], 404);
        }

        try {
            $updatedShipment = ShipmentCreationService::update($shipment, $request->validated());

            return response()->json([
                'message' => 'Shipment updated successfully.',
                'shipment' => $updatedShipment
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Shipment update failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel($id)
    {
        try {
            $shipment = ShipmentCreationService::cancel($id);

            return response()->json([
                'message' => 'Shipment cancelled successfully.',
                'shipment' => $shipment
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Shipment not found '
            ], 404);
        }}

    public function storeRecipient2(StoreRecipientRequest $request)
    {
        $recipientData = $request->validated();

        $cacheKey = 'recipient_data_' . Auth::id();
        Cache::put($cacheKey, $recipientData, now()->addMinutes(10));

        return response()->json([
            'message' => 'Recipient info saved (admin). Proceed to shipment details.'
        ]);
    }

    public function storeshipment(StoreShipmentRequest $request)
    {
        $cacheKey = 'recipient_data_' . Auth::id();
        $recipientData = Cache::get($cacheKey);

        if (!$recipientData) {
            return response()->json(['error' => 'Recipient information not provided'], 422);
        }

        $shipment = ShipmentCreationService::createByAdmin(
            $recipientData,
            $request->validated(),
            $request->input('client_id') // من الريكويست
        );

        return response()->json([
            'message' => 'Shipment created successfully by admin.',
            'shipment' => $shipment,
        ]);
    }
}
