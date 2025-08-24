<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
              CenterSeeder::class,
            RolePermissionSeeder::class,

            ShipmentSeeder::class,
            DriverSeeder::class
        ]);
        $user = User::firstOrCreate([
            'email' => 'client@test.com',
        ], [
            'name' => 'Test Client',
            'password' => Hash::make('password123'), //
            'role' => 'client',
            'is_approved' => true,
            'phone'  => '0938280685',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('client');

        $user = User::firstOrCreate([
            'email' => 'test@a.com',
        ], [
            'name' => 'Test Client',
            'password' => Hash::make('password123'),
'phone'  =>'0994493352',          'role' => 'client',
            'is_approved' => true,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('client');

    }
}
