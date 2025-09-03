<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrailerResource extends JsonResource
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
            'name' => $this->name,
            'status' => $this->status,
            'capacity_kg' => $this->capacity_kg,
            'capacity_m3' => $this->capacity_m3,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
