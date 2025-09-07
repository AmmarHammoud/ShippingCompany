<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ShipmentOfferedToDriver implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public Shipment $shipment;
    public int $driverId;

    public function __construct(Shipment $shipment, int $driverId)
    {
        $this->shipment = $shipment;
        $this->driverId = $driverId;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('driver.' . $this->driverId);
    }

    public function broadcastWith(): array
    {
        return [
            'shipment' => $this->shipment->toArray(),
            'message' => 'You have received a new shipment offer',
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipment.offered';
    }
}
