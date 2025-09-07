<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ShipmentOfferedToDriver implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public Shipment $shipment;
    public int $driverId;
    public $afterCommit = true;

    /**
     * Create a new event instance.
     */
    public function __construct(Shipment $shipment, int $driverId)
    {
        $this->shipment = $shipment;
        $this->driverId = $driverId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        // private channel لكل سائق
        return new PrivateChannel('driver.' . $this->driverId);
    }

    /**
     * البيانات المرسلة عبر البث.
     */
    public function broadcastWith(): array
    {
        return [
            'shipment' => $this->shipment->toArray(),
            'message' => 'You have received a new shipment offer',
        ];
    }

    /**
     * اسم الحدث في الـ client
     */
    public function broadcastAs(): string
    {
        return 'shipment.offered';
    }
}
