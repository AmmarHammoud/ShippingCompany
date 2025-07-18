<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class ShipmentDeliveredBroadcast implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    public Shipment $shipment;

    public function __construct(Shipment $shipment)
    {
        $this->shipment = $shipment;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('client.' . $this->shipment->client_id);
    }

    public function broadcastWith(): array
    {
        return [
            'message' => "Shipment {$this->shipment->barcode} has been marked as delivered.",
            'shipment' => $this->shipment->only([
                'id',
                'barcode',
                'status',
                'recipient_name',
                'updated_at'
            ]),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipment.delivered';
    }
}
