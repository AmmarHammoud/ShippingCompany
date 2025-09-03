<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Center;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverSeeder extends Seeder
{
   public function run(): void
    {
        $centers = Center::select('id', 'name', 'latitude', 'longitude')->get();
        $drivers = [];
        $password = Hash::make('password123');
        $now = now();
        
        foreach ($centers as $center) {
            for ($i = 1; $i <= 5; $i++) {
                $phoneSuffix = mt_rand(1000000, 9999999);
                $latVariation = mt_rand(-30, 30) / 1000;
                $lngVariation = mt_rand(-30, 30) / 1000;

                $drivers[] = [
                    'name' => "Driver_{$center->name}_{$i}",
                    'email' => "driver_{$center->id}_{$i}@example.com",
                    'phone' => "093" . $phoneSuffix,
                    'password' => $password,
                    'role' => 'driver',
                    'center_id' => $center->id,
                    'is_approved' => 1, // Use integer instead of boolean
                    'active' => 1,      // Use integer instead of boolean
                    'latitude' => $center->latitude + $latVariation,
                    'longitude' => $center->longitude + $lngVariation,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        
        // Use DB facade for direct insertion (bypasses Eloquent)
        User::insert($drivers);
    }
}
