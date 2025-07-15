<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Services\NearestCenterService;
use Illuminate\Support\Str;

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

        $delivery_price = 5 + ($shipmentData['weight'] * 1.2);

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
    }
}
