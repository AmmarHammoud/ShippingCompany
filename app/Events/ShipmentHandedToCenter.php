<?php
namespace App\Events;
use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentHandedToCenter implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Shipment $shipment;

    public function __construct(Shipment $shipment)
    {
        $this->shipment = $shipment;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('client.' . $this->shipment->client_id),
            new PrivateChannel('recipient.' . $this->shipment->recipient_id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'shipment_id' => $this->shipment->id,
            'status' => $this->shipment->status,
            'message' => 'Shipment handed to origin center by driver.',
        ];
    }
}
