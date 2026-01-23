<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GpsServer extends Command
{
    protected $signature = 'gps:server {port=5022}';
    protected $description = 'Start TCP Server (Universal Protocol)';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 SERVER READY: Port $port");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $this->info("⚡ Device masuk: " . $connection->getRemoteAddress());
            $connection->on('data', function ($data) use ($connection) {
                $hex = bin2hex($data);
                $text = trim($data);
                $this->processPacket($connection, $hex, $text);
            });
        });

        $loop->run();
    }

    private function processPacket($connection, $hexData, $textData)
    {
        if (str_starts_with($textData, '(') && str_ends_with($textData, ')')) {
            $content = substr($textData, 1, -1);
            $factoryId = substr($content, 0, 12);
            $cmd = substr($content, 12, 4);
            $data = substr($content, 16);

            $this->info("✅ MSG: $cmd | ID: $factoryId");

            // --- 1. LOGIN (BP05) ---
            if ($cmd == 'BP05') {
                $imei = substr($data, 0, 15);
                $this->info("🔑 Login IMEI: $imei");
                $this->updateDeviceStatus($imei);
                $connection->write("(" . $factoryId . "AP05)");
            }

            // --- 2. LOKASI (BR00 ATAU BP04) ---
            // Kita gabungkan logikanya karena format datanya mirip
            elseif ($cmd == 'BR00' || $cmd == 'BP04') {
                
                // Format Regex Universal untuk BP04/BR00
                // Mencari pola: Angka(Waktu) + A/V + Lat + N/S + Lon + E/W + Speed
                // Contoh Data: 260123A0509.2397S11926.2647E000.0...
                if (preg_match('/([AV])(\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $matches)) {
                    
                    $valid = $matches[1];
                    $rawLat = $matches[2];
                    $ns = $matches[3];
                    $rawLng = $matches[4];
                    $ew = $matches[5];
                    $speedKnots = $matches[6];

                    $lat = $this->dmToDecimal($rawLat);
                    $lng = $this->dmToDecimal($rawLng);

                    if ($ns == 'S') $lat = $lat * -1;
                    if ($ew == 'W') $lng = $lng * -1;

                    $speed = floatval($speedKnots) * 1.852;

                    $this->info("📍 LOKASI VALID [$cmd]: $lat, $lng | Speed: $speed");

                    if ($valid == 'A') {
                        // Simpan ke device yang terakhir aktif (Login)
                        $device = DB::table('devices')->orderBy('updated_at', 'desc')->first();
                        
                        if ($device) {
                            DB::table('positions')->insert([
                                'imei' => $device->imei,
                                'latitude' => $lat,
                                'longitude' => $lng,
                                'speed' => $speed,
                                'course' => 0,
                                'gps_time' => Carbon::now(),
                                'created_at' => Carbon::now()
                            ]);
                            $this->info("💾 DATABASE SAVED! Cek Dashboard.");
                        }
                    } else {
                        $this->warn("⚠️ GPS Void (Sinyal Lemah)");
                    }

                    // Reply sesuai command yang masuk
                    if ($cmd == 'BR00') $connection->write("(" . $factoryId . "AR00)");
                    if ($cmd == 'BP04') $connection->write("(" . $factoryId . "AP04)");
                }
            }

            // --- 3. HEARTBEAT & LAINNYA ---
            elseif ($cmd == 'BZ00') $connection->write("(" . $factoryId . "AZ00)");
            elseif ($cmd == 'BP00') $connection->write("(" . $factoryId . "AP00)");
        }
    }

    private function dmToDecimal($dm) {
        $dotPos = strpos($dm, '.');
        if ($dotPos === false) return 0;
        $minutes = substr($dm, $dotPos - 2);
        $degrees = substr($dm, 0, $dotPos - 2);
        return floatval($degrees) + (floatval($minutes) / 60);
    }

    private function updateDeviceStatus($imei) {
        try {
            DB::table('devices')->updateOrInsert(
                ['imei' => $imei],
                ['last_online' => Carbon::now(), 'updated_at' => Carbon::now()]
            );
        } catch (\Exception $e) {}
    }
}