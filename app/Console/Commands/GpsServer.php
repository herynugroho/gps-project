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
    protected $description = 'Start TCP Server with Distance-based Filtering';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 GPS SERVER ANTI-JITTER STARTED ON PORT $port");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connection->on('data', function ($data) use ($connection) {
                $this->processPacket($connection, bin2hex($data), trim($data));
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

            if ($cmd == 'BP05') {
                $imei = substr($data, 0, 15);
                DB::table('devices')->where('imei', $imei)->update(['factory_id' => $factoryId]);
                $connection->write("(" . $factoryId . "AP05)");
            }

            elseif ($cmd == 'BR00' || $cmd == 'BP04') {
                if (preg_match('/([AV])(\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $matches)) {
                    if ($matches[1] == 'A') {
                        $lat = $this->dmToDecimal($matches[2]);
                        if ($matches[3] == 'S') $lat *= -1;
                        $lng = $this->dmToDecimal($matches[4]);
                        if ($matches[5] == 'W') $lng *= -1;
                        $speed = floatval($matches[6]) * 1.852;

                        $device = DB::table('devices')->where('factory_id', $factoryId)->first();
                        if ($device) {
                            $lastPos = DB::table('positions')->where('imei', $device->imei)->orderBy('id', 'desc')->first();
                            
                            $shouldInsert = true;
                            if ($lastPos) {
                                // Hitung jarak sederhana dalam meter (Haversine approximation)
                                $dist = $this->calculateDistance($lastPos->latitude, $lastPos->longitude, $lat, $lng);
                                
                                // JANGAN SIMPAN jika:
                                // 1. Jarak perpindahan < 20 meter DAN speed < 3 km/h
                                if ($dist < 20 && $speed < 3) {
                                    $shouldInsert = false;
                                }
                            }

                            if ($shouldInsert) {
                                DB::table('positions')->insert([
                                    'imei' => $device->imei,
                                    'latitude' => $lat, 'longitude' => $lng, 'speed' => $speed,
                                    'gps_time' => Carbon::now(), 'created_at' => Carbon::now()
                                ]);
                                $this->info("📍 [MOVE] $device->name: $dist m | $speed km/h");
                            } else {
                                $this->info("💤 [STAY] $device->name: diam di radius aman.");
                            }

                            DB::table('devices')->where('imei', $device->imei)->update([
                                'last_online' => Carbon::now(), 'updated_at' => Carbon::now()
                            ]);
                        }
                    }
                    $connection->write("(" . $factoryId . ($cmd == 'BR00' ? "AR00" : "AP04") . ")");
                }
            }
            elseif ($cmd == 'BZ00') $connection->write("(" . $factoryId . "AZ00)");
            elseif ($cmd == 'BP00') $connection->write("(" . $factoryId . "AP00)");
        }
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        return $dist * 60 * 1.1515 * 1.609344 * 1000; // Hasil dalam METER
    }

    private function dmToDecimal($dm) {
        $dotPos = strpos($dm, '.');
        if ($dotPos === false) return 0;
        return floatval(substr($dm, 0, $dotPos - 2)) + (floatval(substr($dm, $dotPos - 2)) / 60);
    }
}