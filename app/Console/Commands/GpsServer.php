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
    protected $description = 'Start TCP Server (Production)';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        
        // Matikan logging ReactPHP yang berlebihan
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 GPS SERVER PRODUCTION STARTED ON PORT $port");

        $socket->on('connection', function (ConnectionInterface $connection) {
            // Log IP Address (Berguna untuk security audit)
            $this->info("[" . Carbon::now()->toTimeString() . "] ⚡ New Connection: " . $connection->getRemoteAddress());
            
            $connection->on('data', function ($data) use ($connection) {
                // Proses data tanpa output berlebihan
                $hex = bin2hex($data);
                $text = trim($data);
                $this->processPacket($connection, $hex, $text);
            });

            $connection->on('close', function () use ($connection) {
                // Optional: Log disconnect
                // $this->info("❌ Disconnected: " . $connection->getRemoteAddress());
            });
            
            $connection->on('error', function (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
            });
        });

        $loop->run();
    }

    private function processPacket($connection, $hexData, $textData)
    {
        // ==========================================================
        // PROTOKOL TEXT (365GPS/TOPIN - GT02A Clone)
        // Format: (SerialID + CMD + DATA)
        // ==========================================================
        if (str_starts_with($textData, '(') && str_ends_with($textData, ')')) {
            $content = substr($textData, 1, -1);
            $factoryId = substr($content, 0, 12);
            $cmd = substr($content, 12, 4);
            $data = substr($content, 16);

            // --- 1. LOGIN (BP05) ---
            if ($cmd == 'BP05') {
                $imei = substr($data, 0, 15);
                $this->updateDeviceStatus($imei);
                $connection->write("(" . $factoryId . "AP05)");
                $this->info("🔑 Login: $imei");
            }

            // --- 2. LOKASI (BR00 / BP04) ---
            elseif ($cmd == 'BR00' || $cmd == 'BP04') {
                // Regex Universal: Waktu + A/V + Lat + N/S + Lon + E/W + Speed
                if (preg_match('/([AV])(\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $matches)) {
                    
                    $valid = $matches[1];
                    
                    if ($valid == 'A') {
                        $lat = $this->dmToDecimal($matches[2]);
                        $ns = $matches[3];
                        $lng = $this->dmToDecimal($matches[4]);
                        $ew = $matches[5];
                        $speed = floatval($matches[6]) * 1.852; // Knots to Km/h

                        if ($ns == 'S') $lat = $lat * -1;
                        if ($ew == 'W') $lng = $lng * -1;

                        // Simpan ke Database (Cari device yang baru aktif)
                        $device = DB::table('devices')->orderBy('updated_at', 'desc')->first();
                        
                        if ($device) {
                            DB::table('positions')->insert([
                                'imei' => $device->imei,
                                'latitude' => $lat,
                                'longitude' => $lng,
                                'speed' => $speed,
                                'course' => 0, // Protocol ini jarang kirim course akurat
                                'gps_time' => Carbon::now(),
                                'created_at' => Carbon::now()
                            ]);
                            
                            // Update last_online
                            DB::table('devices')->where('imei', $device->imei)->update([
                                'last_online' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ]);
                            
                            $this->info("📍 Saved: $lat, $lng ($speed km/h) -> " . $device->imei);
                        }
                    } 
                    // Kita tidak perlu log data "Void/V" agar log bersih
                    
                    // Reply Wajib
                    if ($cmd == 'BR00') $connection->write("(" . $factoryId . "AR00)");
                    if ($cmd == 'BP04') $connection->write("(" . $factoryId . "AP04)");
                }
            }

            // --- 3. HEARTBEAT (BZ00) ---
            elseif ($cmd == 'BZ00') {
                $connection->write("(" . $factoryId . "AZ00)");
                // Heartbeat tidak perlu dicatat di log agar tidak spam
            }

            // --- 4. HANDSHAKE (BP00) ---
            elseif ($cmd == 'BP00') {
                $connection->write("(" . $factoryId . "AP00)");
            }
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