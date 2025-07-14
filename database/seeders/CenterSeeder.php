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
            ['name' => 'دمشق',        'latitude' => 33.5138, 'longitude' => 36.2765],
            ['name' => 'ريف دمشق',    'latitude' => 33.4500, 'longitude' => 36.2000],
            ['name' => 'حلب',         'latitude' => 36.2021, 'longitude' => 37.1343],
            ['name' => 'حمص',         'latitude' => 34.7333, 'longitude' => 36.7167],
            ['name' => 'حماة',         'latitude' => 35.1333, 'longitude' => 36.7500],
            ['name' => 'اللاذقية',     'latitude' => 35.5167, 'longitude' => 35.7833],
            ['name' => 'طرطوس',        'latitude' => 34.8833, 'longitude' => 35.8833],
            ['name' => 'إدلب',         'latitude' => 35.9333, 'longitude' => 36.6333],
            ['name' => 'دير الزور',    'latitude' => 35.3333, 'longitude' => 40.1500],
            ['name' => 'الحسكة',       'latitude' => 36.4833, 'longitude' => 40.7500],
            ['name' => 'الرقة',        'latitude' => 35.9500, 'longitude' => 39.0167],
            ['name' => 'درعا',         'latitude' => 32.6253, 'longitude' => 36.1061],
            ['name' => 'السويداء',     'latitude' => 32.7036, 'longitude' => 36.5660],
            ['name' => 'القنيطرة',     'latitude' => 33.1250, 'longitude' => 35.8200],
        ];

        foreach ($centers as $center) {
            Center::firstOrCreate(['name' => $center['name']], [
                'latitude' => $center['latitude'],
                'longitude' => $center['longitude'],
            ]);
        }
    }


}
