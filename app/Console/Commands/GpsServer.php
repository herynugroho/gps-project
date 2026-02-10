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
    protected $description = 'Start TCP Server with Data Filtering';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 GPS SERVER OPTIMIZED STARTED ON PORT $port");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connection->on('data', function ($data) use ($connection) {
                $text = trim($data);
                $this->processPacket($connection, bin2hex($data), $text);
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

            // --- 1. LOGIN (BP05) ---
            if ($cmd == 'BP05') {
                $imei = substr($data, 0, 15);
                DB::table('devices')->where('imei', $imei)->update(['factory_id' => $factoryId]);
                $this->updateDeviceStatus($imei);
                $connection->write("(" . $factoryId . "AP05)");
                $this->info("🔑 Login: $imei");
            }

            // --- 2. LOKASI (BR00 / BP04) ---
            elseif ($cmd == 'BR00' || $cmd == 'BP04') {
                if (preg_match('/([AV])(\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $matches)) {
                    $valid = $matches[1];
                    if ($valid == 'A') {
                        $lat = $this->dmToDecimal($matches[2]);
                        $ns = $matches[3];
                        $lng = $this->dmToDecimal($matches[4]);
                        $ew = $matches[5];
                        $speed = floatval($matches[6]) * 1.852;

                        if ($ns == 'S') $lat = $lat * -1;
                        if ($ew == 'W') $lng = $lng * -1;

                        $device = DB::table('devices')->where('factory_id', $factoryId)->first();
                        
                        if ($device) {
                            // --- LOGIKA FILTER DATA DUPLIKAT ---
                            $lastPos = DB::table('positions')
                                ->where('imei', $device->imei)
                                ->orderBy('id', 'desc')
                                ->first();

                            // Kita beri toleransi sedikit (sekitar 5-10 meter) untuk getaran GPS
                            $isSameLocation = false;
                            if ($lastPos) {
                                $latDiff = abs($lastPos->latitude - $lat);
                                $lngDiff = abs($lastPos->longitude - $lng);
                                // 0.0001 itu kira-kira 10 meter. Jika dibawah itu, anggap diam.
                                if ($latDiff < 0.00005 && $lngDiff < 0.00005 && $speed < 1) {
                                    $isSameLocation = true;
                                }
                            }

                            if (!$isSameLocation) {
                                // Hanya simpan jika posisi berubah atau sedang bergerak
                                DB::table('positions')->insert([
                                    'imei' => $device->imei,
                                    'latitude' => $lat,
                                    'longitude' => $lng,
                                    'speed' => $speed,
                                    'gps_time' => Carbon::now(),
                                    'created_at' => Carbon::now()
                                ]);
                                $this->info("📍 [SIMPAN] $device->name pindah ke $lat, $lng");
                            } else {
                                // Jika diam, kita tidak simpan ke table positions, tapi...
                                // Kita beri info di console agar kita tahu alat tetap konek
                                $this->info("💤 [DIAM] $device->name tidak berpindah posisi.");
                            }

                            // SELALU update last_online di table devices agar di peta tetap "LIVE"
                            DB::table('devices')->where('imei', $device->imei)->update([
                                'last_online' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ]);
                        }
                    }
                    if ($cmd == 'BR00') $connection->write("(" . $factoryId . "AR00)");
                    if ($cmd == 'BP04') $connection->write("(" . $factoryId . "AP04)");
                }
            }
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
        DB::table('devices')->where('imei', $imei)->update([
            'last_online' => Carbon::now(), 
            'updated_at' => Carbon::now()
        ]);
    }
}