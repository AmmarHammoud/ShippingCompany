<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AmmarResource extends JsonResource
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
            'barcode' => $this->barcode,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'this_type' => $this->this_type,
            'number_of_pieces' => $this->number_of_pieces,
            'weight' => $this->weight,
            'delivery_price' => $this->delivery_price,
            'product_value' => $this->product_value,
            'total_amount' => $this->total_amount,
            'qr_code_url' => $this->qr_code_url,
            'created_at' => $this->created_at,
            'sender_location' => [
                'lat' => $this->sender_lat,
                'lng' => $this->sender_lng,
            ],
            'recipient_location' => [
                'lat' => $this->recipient_lat,
                'lng' => $this->recipient_lng,
                'description' => $this->recipient_location,
            ],
            'recipient' => [
                'id' => $this->recipient?->id,
                'name' => $this->recipient?->name,
                'phone' => $this->recipient?->phone,
                'email' => $this->recipient?->email,
            ],
            'client' => [
                'id' => $this->client?->id,
                'name' => $this->client?->name,
                'phone' => $this->client?->phone,
                'email' => $this->client?->email,
            ],
            'rating' => $this->rating,
            'isPaid' => $this->isPaid()
        ];
    }
}
