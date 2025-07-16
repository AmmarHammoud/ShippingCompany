<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ShipmentOfferResponded implements ShouldBroadcast
{
    use SerializesModels;

    public Shipment $shipment;
    public string $response;

    public function __construct(Shipment $shipment, string $response)
    {
        $this->shipment = $shipment;
        $this->response = $response; // accepted | rejected
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('client.' . $this->shipment->client_id);
    }

    public function broadcastWith(): array
    {
        return [
            'message' => "Your shipment was {$this->response} by a driver.",
            'shipment' => $this->shipment->toArray(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipment.response';
    }
}

