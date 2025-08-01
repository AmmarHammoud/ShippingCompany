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
        $centers = Center::all();

        foreach ($centers as $center) {
            for ($i = 1; $i <= 5; $i++) {
                User::create([
                    'name' => "Driver_{$center->name}_{$i}",
                    'email' => "driver_{$center->id}_{$i}@example.com",
                    'phone' => "093" . rand(1000000, 9999999),
                    'password' => Hash::make('password123'),
                    'role' => 'driver',
                    'center_id' => $center->id,
                    'is_approved' => true,
                    'active' => true,
                    'latitude' => $center->latitude + (mt_rand(-30, 30) / 1000), // ±0.03 تقريبًا 3 كم
                    'longitude' => $center->longitude + (mt_rand(-30, 30) / 1000),
                    'email_verified_at' => now(),
                ]);
            }
        }
    }
}
