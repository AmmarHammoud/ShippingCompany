<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\AmmarResource;

class OffersResource extends JsonResource
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
            'shipment_id' => $this->shipment_id,
            'driver_id' => $this->driver_id,
            'status' => $this->status,
            'stage' => $this->stage,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'shipment' => new AmmarResource($this->whenLoaded('shipment')),
        ];
    }
}
