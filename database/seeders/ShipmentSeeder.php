<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Shipment;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use database\factories\ShipmentFactory;
class ShipmentSeeder extends Seeder
{
    public function run()
    {
        // Create necessary relationships first

        $clients = User::factory(3)->create(['role' => 'client'])
            ->each(fn ($user) => $user->assignRole('client'));

        $recipients = User::factory(5)->create(['role' => 'client'])
            ->each(fn ($user) => $user->assignRole('client')); 

        $drivers = User::factory(2)->create(['role' => 'driver'])
            ->each(fn ($user) => $user->assignRole('driver'));

        $centers = Center::all();
        
        $shipments = Shipment::factory()->count(15)->create([
            'client_id' => fn() => $clients->random()->id,
            'center_from_id' => fn() => $centers->random()->id,
            'center_to_id' => fn() => $centers->random()->id,
            'pickup_driver_id' => fn() => $drivers->random()->id,
            'delivery_driver_id' => fn() => $drivers->random()->id,
            'recipient_id' => fn() => $recipients->random()->id,
            'invoice_number' => fn() => 'INV-' . Str::upper(Str::random(10)),
            'barcode' => fn() => 'BRC-' . Str::upper(Str::random(12)),
            'qr_code_url' => fn() => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . Str::uuid(),
        ]);
        foreach($shipments as $shipment) {
            if($shipment->status == 'delivered') $shipment->delivered_at = now();
            $shipment->save();
        }
    }
}