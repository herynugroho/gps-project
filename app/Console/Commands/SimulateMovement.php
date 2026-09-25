<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimulateMovement extends Command
{
    protected $signature = 'gps:simulate';
    protected $description = 'Menggerakkan mobil dummy di sekitar Makassar';

    public function handle()
    {
        $this->info("🚗 Memulai Simulasi Pergerakan Armada di Makassar...");
        
        while (true) {
            $devices = DB::table('devices')->get();

            foreach ($devices as $device) {
                // Ambil posisi terakhir
                $lastPos = DB::table('positions')
                    ->where('imei', $device->imei)
                    ->orderBy('id', 'desc')
                    ->first();

                if (!$lastPos) continue;

                // Logika Pergerakan Acak (Jalan-jalan santai)
                $lat = $lastPos->latitude;
                $lng = $lastPos->longitude;
                
                // Random speed 10 - 80 km/h
                $speed = rand(10, 80); 
                
                // Ubah arah sedikit-sedikit
                $lat += (rand(-5, 5) / 10000); 
                $lng += (rand(-5, 5) / 10000);
                $course = rand(0, 360);

                $now = Carbon::now('Asia/Makassar');

                // Update Posisi Baru ke Database
                DB::table('positions')->insert([
                    'imei'       => $device->imei,
                    'latitude'   => $lat,
                    'longitude'  => $lng,
                    'speed'      => $speed,
                    'course'     => $course,
                    'gps_time'   => $now,
                    'created_at' => $now,
                ]);

                // Update status device dan denormalized coordinate jadi Online & Moving
                DB::table('devices')->where('imei', $device->imei)->update([
                    'last_latitude'  => $lat,
                    'last_longitude' => $lng,
                    'last_speed'     => $speed,
                    'last_gps_time'  => $now,
                    'acc_status'     => 1,
                    'last_online'    => $now,
                    'updated_at'     => $now,
                ]);

                $this->info("Mobil {$device->name} bergerak ke [$lat, $lng] Speed: $speed");
            }

            // Tunggu 2 detik sebelum update lagi
            sleep(2);
        }
    }
}