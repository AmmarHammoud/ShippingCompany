<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Trailer;

class TrailerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Status options for trailers
        $statuses = ['available', 'in_use', 'maintenance'];
        
        // (kg per m³ is approximately 250-300 for general cargo)
        $trailerTypes = [
            ['name' => 'Small Box Trailer', 'kg' => 5000, 'm3' => 20],
            ['name' => 'Standard Box Trailer', 'kg' => 10000, 'm3' => 40],
            ['name' => 'Large Box Trailer', 'kg' => 15000, 'm3' => 60],
            ['name' => 'Jumbo Trailer', 'kg' => 20000, 'm3' => 80],
            ['name' => 'Flatbed Trailer', 'kg' => 25000, 'm3' => 30], // Lower m3 for flatbeds
            ['name' => 'Refrigerated Trailer', 'kg' => 12000, 'm3' => 35], // Insulated walls reduce space
        ];
        
        // For each center (assuming centers have IDs from 1 to 14)
        for ($centerId = 1; $centerId <= 14; $centerId++) {
            // Create 10 trailers for this center
            for ($i = 1; $i <= 10; $i++) {
                $trailerType = $trailerTypes[array_rand($trailerTypes)];
                
                // Add slight variations to capacities (±10%)
                $capacityKg = $trailerType['kg'] * (0.9 + (mt_rand(0, 200) / 1000));
                $capacityM3 = $trailerType['m3'] * (0.9 + (mt_rand(0, 200) / 1000));
                
                Trailer::create([
                    'name' => $trailerType['name'] . ' ' . $i . ' - Center ' . $centerId,
                    'center_id' => $centerId,
                    'status' => $statuses[array_rand($statuses)],
                    'capacity_kg' => round($capacityKg, 2),
                    'capacity_m3' => round($capacityM3, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
