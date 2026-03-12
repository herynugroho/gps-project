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
    protected $description = 'Super Hybrid Server for Standard Text and Concox GT06N Binary';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 PRIMA GPS SUPER HYBRID SERVER STARTED ON PORT $port");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connection->on('data', function ($data) use ($connection) {
                $this->handleIncomingData($connection, $data);
            });
        });

        $loop->run();
    }

    private function handleIncomingData($connection, $raw)
    {
        $hex = bin2hex($raw);
        $text = trim($raw);

        // --- 1. DETEKSI PROTOKOL TEKS (MODUL LAMA) ---
        if (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $this->parseTextProtocol($connection, $text);
        } 
        
        // --- 2. DETEKSI PROTOKOL BINER CONCOX (GT06N) ---
        // Biasanya dimulai dengan 7878 (Start Bit)
        elseif (str_starts_with($hex, '7878')) {
            $this->parseBinaryProtocol($connection, $hex);
        }
        
        else {
            $this->warn("❓ Unknown Data Received: " . $hex);
        }
    }

    // LOGIKA PARSER LAMA (TETAP DIJAGA AGAR KOTA 1 & 2 TETAP JALAN)
    private function parseTextProtocol($connection, $text)
    {
        $content = substr($text, 1, -1);
        $factoryId = substr($content, 0, 12);
        $cmd = substr($content, 12, 4);
        $data = substr($content, 16);

        $device = DB::table('devices')->where('factory_id', $factoryId)->first();

        if ($cmd == 'BP05') { // Login
            $imei = substr($data, 0, 15);
            DB::table('devices')->where('imei', $imei)->update(['factory_id' => $factoryId]);
            $connection->write("(" . $factoryId . "AP05)");
            $this->info("🔑 Text Login: $imei");
        } 
        elseif (($cmd == 'BR00' || $cmd == 'BP04') && $device) {
            if (preg_match('/([AV])(\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $matches)) {
                $lat = $this->dmToDecimal($matches[2]) * ($matches[3] == 'S' ? -1 : 1);
                $lng = $this->dmToDecimal($matches[4]) * ($matches[5] == 'W' ? -1 : 1);
                $speed = floatval($matches[6]) * 1.852;
                $this->savePosition($device, $lat, $lng, $speed, ($speed > 5 ? 1 : 0));
                $connection->write("(" . $factoryId . ($cmd == 'BR00' ? "AR00" : "AP04") . ")");
            }
        }
    }

    // LOGIKA PARSER BARU (KHUSUS GT06N 4G)
    private function parseBinaryProtocol($connection, $hex)
    {
        $protocolId = substr($hex, 6, 2);

        // ID 01: Login Packet
        if ($protocolId == '01') {
            $terminalId = substr($hex, 8, 16); // Ambil Terminal ID (Seringkali bagian dari IMEI)
            $this->info("🔑 GT06N Login Attempt: $terminalId");
            
            // Balas Login (Format: 78 78 05 01 [Serial] [Error Check] 0D 0A)
            $response = hex2bin('78780501' . substr($hex, -12, 4) . '00000d0a');
            $connection->write($response);
        } 
        
        // ID 22: GPS Location Packet (Untuk GT06 4G)
        elseif ($protocolId == '22' || $protocolId == '12') {
            // Parsing Latitude, Longitude, Speed dari format biner
            $latHex = substr($hex, 22, 8);
            $lngHex = substr($hex, 30, 8);
            $speedHex = substr($hex, 38, 2);
            $statusHex = substr($hex, 60, 2); // Bit status ACC biasanya di sini

            $lat = hexdec($latHex) / 1800000;
            $lng = hexdec($lngHex) / 1800000;
            $speed = hexdec($speedHex);
            $accStatus = (hexdec($statusHex) & 0x02) ? 1 : 0; // Deteksi ACC Bit

            // Cari device berdasarkan factory_id atau Terminal ID yang mirip
            // Kita coba cari yang mengandung potongan ID biner tersebut
            $device = DB::table('devices')
                ->where('factory_id', 'LIKE', '%' . substr($hex, 8, 8) . '%')
                ->orWhere('imei', 'LIKE', '%' . substr($hex, 8, 8) . '%')
                ->first();

            if ($device) {
                $this->savePosition($device, $lat, $lng, $speed, $accStatus);
                $this->info("📍 GT06N Update: $device->name | ACC: $accStatus");
            }
        }
    }

    private function savePosition($device, $lat, $lng, $speed, $acc)
    {
        $speed = ($speed < 5) ? 0 : $speed;
        
        DB::table('positions')->insert([
            'imei' => $device->imei, 'latitude' => $lat, 'longitude' => $lng,
            'speed' => $speed, 'gps_time' => Carbon::now(), 'created_at' => Carbon::now()
        ]);

        DB::table('devices')->where('imei', $device->imei)->update([
            'acc_status' => $acc, 'last_online' => Carbon::now(), 'updated_at' => Carbon::now()
        ]);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        return rad2deg(acos($dist)) * 60 * 1.1515 * 1.609344 * 1000;
    }

    private function dmToDecimal($dm) {
        $dotPos = strpos($dm, '.');
        return floatval(substr($dm, 0, $dotPos - 2)) + (floatval(substr($dm, $dotPos - 2)) / 60);
    }
}