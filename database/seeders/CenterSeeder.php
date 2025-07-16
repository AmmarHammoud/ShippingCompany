<?php

namespace Database\Seeders;

use App\Models\Center;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $centers = [
            ['name' => 'Damascus',        'latitude' => 33.5138, 'longitude' => 36.2765],
            ['name' => 'Rural Damascus',  'latitude' => 33.4500, 'longitude' => 36.2000],
            ['name' => 'Aleppo',          'latitude' => 36.2021, 'longitude' => 37.1343],
            ['name' => 'Homs',            'latitude' => 34.7333, 'longitude' => 36.7167],
            ['name' => 'Hama',            'latitude' => 35.1333, 'longitude' => 36.7500],
            ['name' => 'Latakia',         'latitude' => 35.5167, 'longitude' => 35.7833],
            ['name' => 'Tartous',         'latitude' => 34.8833, 'longitude' => 35.8833],
            ['name' => 'Idlib',           'latitude' => 35.9333, 'longitude' => 36.6333],
            ['name' => 'Deir ez-Zor',     'latitude' => 35.3333, 'longitude' => 40.1500],
            ['name' => 'Hasakah',         'latitude' => 36.4833, 'longitude' => 40.7500],
            ['name' => 'Raqqa',           'latitude' => 35.9500, 'longitude' => 39.0167],
            ['name' => 'Daraa',           'latitude' => 32.6253, 'longitude' => 36.1061],
            ['name' => 'As-Suwayda',      'latitude' => 32.7036, 'longitude' => 36.5660],
            ['name' => 'Quneitra',        'latitude' => 33.1250, 'longitude' => 35.8200],
        ];
        foreach ($centers as $center) {
            Center::firstOrCreate(['name' => $center['name']], [
                'latitude' => $center['latitude'],
                'longitude' => $center['longitude'],
            ]);
        }
    }


}
