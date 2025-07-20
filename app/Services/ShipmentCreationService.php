<?php

namespace App\Services;

use App\Events\ShipmentDelivered;
use App\Events\ShipmentDeliveredBroadcast;
use App\Events\ShipmentHandedToCenter;
use App\Models\Shipment;
use App\Models\shipmentratings;
use App\Models\User;
use App\Services\NearestCenterService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentCreationService
{
    public static function create(array $recipientData, array $shipmentData, User $client): Shipment
    {
        $recipient = User::where('phone', $recipientData['recipient_phone'])->firstOrFail();


        if (! $recipient) {
            throw ValidationException::withMessages([
                'recipient_phone' => ['  recipient not found']
            ]);
        }
        $centerFrom = NearestCenterService::getNearestCenter(
            $shipmentData['sender_lat'],
            $shipmentData['sender_lng']
        );

        $centerTo = NearestCenterService::getNearestCenter(
            $recipientData['recipient_lat'],
            $recipientData['recipient_lng']
        );

        $lastId = Shipment::max('id') ?? 0;
        $invoice = 'INV-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
        $barcode = strtoupper(Str::random(10));

        $distance_sender_to_center = OfferShipmentToNearestDriverService::calculateDistance(
            $shipmentData['sender_lat'],
            $shipmentData['sender_lng'],
            $centerFrom->latitude,
            $centerFrom->longitude
        );

        $distance_center_to_center = OfferShipmentToNearestDriverService::calculateDistance(
            $centerFrom->latitude,
            $centerFrom->longitude,
            $centerTo->latitude,
            $centerTo->longitude
        );

        $total_distance = $distance_sender_to_center + $distance_center_to_center;
        $exchange_rate = 10000;
        $base_fee = 5;
        $distance_fee = $total_distance * 0.05;
        $weight_fee = $shipmentData['weight'] * 0.2;

        $delivery_price_usd = $base_fee + $distance_fee + $weight_fee;
        $delivery_price = $delivery_price_usd * $exchange_rate;

        $shipment = Shipment::create([
            'client_id' => $client->id,
            'recipient_id'     => $recipient->id,
            'recipient_phone' => $recipient->phone,
            'center_from_id' => $centerFrom->id,
            'center_to_id'   => $centerTo->id,
            'sender_lat'     => $shipmentData['sender_lat'],
            'sender_lng'     => $shipmentData['sender_lng'],
            'recipient_location' => $recipientData['recipient_location'],
            'recipient_lat' => $recipientData['recipient_lat'],
            'recipient_lng' => $recipientData['recipient_lng'],
            'shipment_type' => $shipmentData['shipment_type'],
            'number_of_pieces' => $shipmentData['number_of_pieces'],
            'weight' => $shipmentData['weight'],
            'delivery_price' => $delivery_price,
            'product_value' => $shipmentData['product_value'],
            'total_amount' => $shipmentData['product_value'] + $delivery_price,
            'invoice_number' => $invoice,
            'barcode' => $barcode,
            'status' => 'offered_pickup_driver',
        ]);

        $confirmationUrl = url("/shipments/{$barcode}/confirm");
        $qrImage = QrCode::format('svg')->size(300)->generate($confirmationUrl);
        $filePath = "qr_codes/shipment_{$shipment->id}.svg";
        Storage::disk('public')->put($filePath, $qrImage);
        $shipment->update(['qr_code_url' => Storage::url($filePath)]);

        OfferShipmentToNearestDriverService::offer($shipment, 'pickup');

        return $shipment;
    }

    public static function update(Shipment $shipment, array $data): Shipment
    {
        if (!in_array($shipment->status, ['pending', 'offered_pickup_driver'])) {
            throw ValidationException::withMessages([
                'status' => ['Cannot update shipment after pickup.']
            ]);
        }

        $mustRecalculate = false;

        if (isset($data['recipient_lat'], $data['recipient_lng'])) {
            $mustRecalculate = true;

            $newCenterTo = NearestCenterService::getNearestCenter(
                $data['recipient_lat'],
                $data['recipient_lng']
            );

            $shipment->center_to_id = $newCenterTo->id;
            $shipment->recipient_lat = $data['recipient_lat'];
            $shipment->recipient_lng = $data['recipient_lng'];
            $shipment->recipient_location = $data['recipient_location'] ?? $shipment->recipient_location;
        }

        if (isset($data['weight'])) {
            $mustRecalculate = true;
            $shipment->weight = $data['weight'];
        }

        if ($mustRecalculate) {
            $shipment->load('centerFrom', 'centerTo');
            $centerFrom = $shipment->centerFrom;
            $centerTo = $shipment->centerTo;

            $distance_sender_to_center = OfferShipmentToNearestDriverService::calculateDistance(
                $shipment->sender_lat,
                $shipment->sender_lng,
                $centerFrom->latitude,
                $centerFrom->longitude
            );

            $distance_center_to_center = OfferShipmentToNearestDriverService::calculateDistance(
                $centerFrom->latitude,
                $centerFrom->longitude,
                $centerTo->latitude,
                $centerTo->longitude
            );

            $total_distance = $distance_sender_to_center + $distance_center_to_center;

            $base_fee     = 5;
            $distance_fee = $total_distance * 0.05;
            $weight_fee   = $shipment->weight * 0.2;
            $delivery_price_usd = $base_fee + $distance_fee + $weight_fee;

            $exchange_rate  = 10000;
            $new_delivery_price = round($delivery_price_usd * $exchange_rate, 2);

            $old_delivery_price = $shipment->delivery_price;

            $shipment->delivery_price = $new_delivery_price;
            $shipment->total_amount = $shipment->total_amount - $old_delivery_price + $new_delivery_price;
        }

        $shipment->fill($data)->save();

        return $shipment;
    }


    public static function cancel(int $shipmentId): Shipment
    {
        $shipment = Shipment::where('id', $shipmentId)
            ->where('client_id', Auth::id())
            ->firstOrFail();

        if (!in_array($shipment->status, ['pending', 'offered_pickup_driver'])) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel shipment after it has been picked up.']
            ]);
        }

        $shipment->update([
            'status' => 'cancelled',
        ]);

        return $shipment;
    }




    public static function findByBarcodeOrId(string|int $identifier): Shipment
    {
        $shipment = is_numeric($identifier)
            ? Shipment::where('id', $identifier)->with('recipient')->first()
            : Shipment::where('barcode', $identifier)->first();

        if (!$shipment) {
            throw new ModelNotFoundException("Shipment not found.");
        }

        return $shipment;
    }

    public static function getShipmentsByClient(User $client)
    {
        return Shipment::with(['recipient', 'centerFrom', 'centerTo'])
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->get();

    }

    public static function confirmByBarcode(string $barcode)
    {
        $shipment = Shipment::where('barcode', $barcode)->with('recipient')->first();

        if (! $shipment) {
            throw ValidationException::withMessages([
                'barcode' => ['Shipment not found.']
            ]);
        }

        if ($shipment->status === 'delivered') {
            return $shipment;
        }

        $shipment->update(['status' => 'delivered',
            'delivered_at' => now(),
        ]);
        broadcast(new ShipmentHandedToCenter($shipment));
    }

}
