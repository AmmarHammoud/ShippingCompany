<?php

namespace App\Http\Controllers;

use App\Events\ShipmentDeliveredBroadcast;
use App\Http\Requests\ShipmentUpdateRequest;
use App\Http\Requests\StoreRecipientRequest;
use App\Http\Requests\StoreShipmentDetailsRequest;
use App\Http\Requests\StoreShipmentRatingRequest;
use App\Models\Shipment;
use App\Services\ShipmentCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

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
                'product_value' => $shipment->product_value,
                'total_amount' => $shipment->total_amount,
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
            'confirmation_url' => url("/shipments/{$shipment->barcode}/confirm")

        ]);
    }


    public function show(string $identifier)
    {
        try {
            $shipment = ShipmentCreationService::findByBarcodeOrId($identifier);

            return response()->json([
                'shipment' => [
                    $shipment->toArray(),
                    'sender_name' => $shipment->client->name,
                    'recipient_name' =>$shipment->recipient->recipient_name ]          ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Shipment not found.'
            ], 404);
        }}


    public function myShipments(Request $request){

        $client = Auth::user();

     $shipments = ShipmentCreationService::getShipmentsByClient($client);

return response()->json([
    'shipments' => $shipments->map(function ($shipment) {
        return [
            'id' => $shipment->id,
            'barcode' => $shipment->barcode,
            'status' => $shipment->status,
            'shipment_type' => $shipment->shipment_type,
            'product_value' => $shipment->product_value,
            'delivery_price' => $shipment->delivery_price,
            'total_amount' => $shipment->total_amount,
            'created_at' => $shipment->created_at->toDateTimeString(),

            'recipient' => [
                'id'       => $shipment->recipient?->id,
                'name'     => $shipment->recipient?->name,
                'location' => $shipment->recipient_location,
                'lat'      => $shipment->recipient_lat,
                'lng'      => $shipment->recipient_lng,
            ],
            'center_from' => $shipment->centerFrom?->name,
            'center_to'   => $shipment->centerTo?->name,
        ];
    }),
]);}

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
            ->firstOrFail();

        $updatedShipment = ShipmentCreationService::update($shipment, $request->validated());

        return response()->json([
            'message' => 'Shipment updated successfully.',
            'shipment' => $updatedShipment
        ]);}


      public function cancel($id)
{
    $shipment = ShipmentCreationService::cancel($id);

    return response()->json([
        'message' => 'Shipment cancelled successfully.',
        'shipment' => $shipment
    ]);

}

    public function rating(StoreShipmentRatingRequest $request)
    {
        try {
            $rating = $this->shipmentRatingService->store($request->validated());

            return response()->json([
                'message' => 'Shipment rated successfully.',
                'data' => $rating
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
}}
