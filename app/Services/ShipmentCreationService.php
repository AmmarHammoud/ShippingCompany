<?php

namespace App\Services;

use App\Events\ShipmentDelivered;
use App\Events\ShipmentDeliveredBroadcast;
use App\Models\Shipment;
use App\Models\User;
use App\Services\NearestCenterService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Cache\Store;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentCreationService
{
    public static function create(array $recipientData, array $shipmentData, User $client): Shipment
    {
        $center = NearestCenterService::getNearestCenter(
            $shipmentData['sender_lat'],
            $shipmentData['sender_lng']
        );

        $lastId = Shipment::max('id') ?? 0;
        $invoice = 'INV-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
        $barcode = strtoupper(Str::random(10));

        // حساب المسافات (كم)
        $distance_sender_to_center = OfferShipmentToNearestDriverService::calculateDistance(
            $shipmentData['sender_lat'],
            $shipmentData['sender_lng'],
            $center->latitude,
            $center->longitude
        );

        $distance_center_to_recipient = OfferShipmentToNearestDriverService::calculateDistance(
            $center->latitude,
            $center->longitude,
            $recipientData['recipient_lat'],
            $recipientData['recipient_lng']
        );

        $total_distance = $distance_sender_to_center + $distance_center_to_recipient;

        //  حساب تكلفة التوصيل
        $delivery_price = 5 + ($total_distance * 1.0) + ($shipmentData['weight'] * 0.5);


        return Shipment::create([
            'client_id' => $client->id,
            'sender_lat' => $shipmentData['sender_lat'],
            'sender_lng' => $shipmentData['sender_lng'],
            'recipient_name' => $recipientData['recipient_name'],
            'recipient_phone' => $recipientData['recipient_phone'],
            'recipient_location' => $recipientData['recipient_location'],
            'recipient_lat' => $recipientData['recipient_lat'],
            'recipient_lng' => $recipientData['recipient_lng'],
            'shipment_type' => $shipmentData['shipment_type'],
            'number_of_pieces' => $shipmentData['number_of_pieces'],
            'weight' => $shipmentData['weight'],
            'delivery_price' => $delivery_price,
            'total_amount' => $shipmentData['total_amount'] + $delivery_price,
            'invoice_number' => $invoice,
            'barcode' => $barcode,
            'status' => 'offered_to_drivers',
        ]);

        $confirmationUrl = url("/shipments/{$barcode}/confirm");

        $qrImage = QrCode::format('png')->size(300)->generate($confirmationUrl);
        $filePath = "qr_codes/shipment_{$shipment->id}.png";
        Storage::disk('public')->put($filePath, $qrImage);


        $shipment->update([
            'qr_code_url' => Storage::url($filePath),
        ]);

        // عرض الشحنة على السائق الأقرب
        OfferShipmentToNearestDriverService::offerToNearestDriver($shipment);

        return $shipment;
    }
    public static function confirmByBarcode(string $barcode)
    {
        $shipment = Shipment::where('barcode', $barcode)->first();

        if (! $shipment) {
            throw ValidationException::withMessages([
                'barcode' => ['Shipment not found.']
            ]);
        }

        if ($shipment->status === 'delivered') {
            return $shipment;
        }

        $shipment->update(['status' => 'delivered']);
        broadcast(new ShipmentDeliveredBroadcast($shipment));
    }


}
