<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeviceSeeder extends Seeder
{
    public function run()
    {
        // Bersihkan data lama
        DB::table('positions')->truncate();
        DB::table('devices')->truncate();

        $fleets = [
            ['imei' => '111111111111111', 'name' => 'Grand Max Logistik', 'plate' => 'DD 8492 KA', 'type' => 'truck'],
            ['imei' => '222222222222222', 'name' => 'Avanza Operasional', 'plate' => 'DD 1222 OA', 'type' => 'car'],
            ['imei' => '333333333333333', 'name' => 'Hino 500 Heavy', 'plate' => 'DD 9999 XY', 'type' => 'truck'],
            ['imei' => '444444444444444', 'name' => 'Motor Kurir 01', 'plate' => 'DD 2231 MM', 'type' => 'motorcycle'],
            ['imei' => '555555555555555', 'name' => 'Hilux Site Manager', 'plate' => 'DD 5555 PS', 'type' => 'car'],
        ];

        foreach ($fleets as $fleet) {
            DB::table('devices')->insert([
                'imei' => $fleet['imei'],
                'name' => $fleet['name'],
                'plate_number' => $fleet['plate'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Posisi Awal (Sekitar Pantai Losari Makassar)
            DB::table('positions')->insert([
                'imei' => $fleet['imei'],
                'latitude' => -5.147665 + (rand(-100, 100) / 10000),
                'longitude' => 119.432731 + (rand(-100, 100) / 10000),
                'speed' => 0,
                'course' => rand(0, 360),
                'gps_time' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]);
        }
    }
}