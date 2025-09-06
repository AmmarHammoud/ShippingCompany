<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchOfferedToDriver
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $driver;
    public $shipmentIds;
    public $stage;

    /**
     * Create a new event instance.
     */
    public function __construct(User $driver, array $shipmentIds, string $stage)
    {
        $this->driver = $driver;
        $this->shipmentIds = $shipmentIds;
        $this->stage = $stage;
    }
}
