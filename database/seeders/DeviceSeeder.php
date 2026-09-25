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
            $initialLat = -5.147665 + (rand(-100, 100) / 10000);
            $initialLng = 119.432731 + (rand(-100, 100) / 10000);
            $now = Carbon::now('Asia/Makassar');

            DB::table('devices')->insert([
                'imei'           => $fleet['imei'],
                'name'           => $fleet['name'],
                'plate_number'   => $fleet['plate'],
                'module_type'    => 'GT06N',
                'fuel_ratio'     => 10.00,
                'acc_status'     => 1,
                'fuel_status'    => 1,
                'last_latitude'  => $initialLat,
                'last_longitude' => $initialLng,
                'last_speed'     => 0,
                'last_gps_time'  => $now,
                'last_online'    => $now,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            // Posisi Awal (Sekitar Pantai Losari Makassar)
            DB::table('positions')->insert([
                'imei'       => $fleet['imei'],
                'latitude'   => $initialLat,
                'longitude'  => $initialLng,
                'speed'      => 0,
                'course'     => rand(0, 360),
                'gps_time'   => $now,
                'created_at' => $now,
            ]);
        }
    }
}