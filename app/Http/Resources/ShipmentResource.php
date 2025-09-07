<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'center_from_id' => $this->center_from_id,
            'center_to_id' => $this->center_to_id,
            'center_from' => $this->centerFrom->name,
            'center_to' => $this->centerTo->name,
            'pickup_driver_id' => $this->pickup_driver_id,
            'delivery_driver_id' => $this->delivery_driver_id,
            // 'sender_lat' => $this->sender_lat,
            // 'sender_lng' => $this->sender_lng,
            // 'recipient_id' => $this->recipient_id,
            // 'recipient_location' => $this->recipient_location,
            // 'recipient_lat' => $this->recipient_lat,
            // 'recipient_lng' => $this->recipient_lng,
            'this_type' => $this->this_type,
            'number_of_pieces' => $this->number_of_pieces,
            'weight' => $this->weight,
            'delivery_price' => $this->delivery_price,
            'product_value' => $this->product_value,
            'total_amount' => $this->total_amount,
            'invoice_number' => $this->invoice_number,
            'barcode' => $this->barcode,
            'status' => $this->status,
            'qr_code_url' => $this->qr_code_url,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // بيانات المرسل
            'sender' => [
                'id' => $this->client?->id,
                'name' => $this->client?->name,
                'email' => $this->client?->email,
                'phone' => $this->client?->phone,
                'lat' => $this->sender_lat,
                'lng' => $this->sender_lng,
            ],

            'recipient' => [
                'id' => $this->recipient?->id,
                'name' => $this->recipient?->name,
                'email' => $this->recipient?->email,
                'phone' => $this->recipient?->phone,
                'location' => $this->recipient_location,
                'lat' => $this->recipient_lat,
                'lng' => $this->recipient_lng,
            ],
            'rating' => $this->rating,
            'isPaid' => $this->isPaid()
        ];
    }
}
