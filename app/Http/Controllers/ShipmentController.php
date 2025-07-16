<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipientRequest;
use App\Http\Requests\StoreShipmentDetailsRequest;
use App\Services\ShipmentCreationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ShipmentController extends Controller
{
    public function storeRecipient(StoreRecipientRequest $request)
    {
        Session::put('recipient_data', $request->validated());

        return response()->json([
            'message' => 'Recipient info saved. Proceed to shipment details.'
        ]);
    }

    public function storeDetails(StoreShipmentDetailsRequest $request)
    {
        $recipientData = Session::get('recipient_data');

        if (!$recipientData) {
            return response()->json(['error' => 'Recipient information not provided'], 422);
        }

        $shipment = ShipmentCreationService::create(
            $recipientData,
            $request->validated(),
            Auth::user()
        );

        Session::forget('recipient_data');

        return response()->json([
            'message' => 'Shipment created successfully.',
            'shipment' => $shipment
        ]);
    }

    public function confirmDelivery($barcode)
    {
        try {
            $shipment = ShipmentCreationService::confirmByBarcode($barcode);

            return response()->json([
                'message' => 'Shipment receipt confirmed',
                'shipment_id' => $shipment->id,
                'recipient_name' => $shipment->recipient_name,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'shipment not found '], 404);
        }
    }
}
