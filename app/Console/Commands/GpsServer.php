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
    protected $description = 'GPS Server Optimized for GT06N 4G (Binary 7878/7979) and Standard Text';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 PRIMA GPS HYBRID SERVER [4G READY] STARTED ON PORT $port");
        $this->info("-------------------------------------------------------");

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

        // 1. PROTOKOL BINER CONCOX (7878 / 7979)
        if (str_starts_with($hex, '7878') || str_starts_with($hex, '7979')) {
            $this->parseBinaryProtocol($connection, $hex);
        }
        // 2. PROTOKOL TEKS (MODUL LAMA)
        elseif (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $this->parseTextProtocol($connection, $text);
        }
    }

    private function parseBinaryProtocol($connection, $hex)
    {
        $is4G = str_starts_with($hex, '7979');
        
        // Offset: 4G (7979) punya 2 byte length, 2G (7878) punya 1 byte length
        $protocolIdPos = $is4G ? 6 : 4;
        $protocolId = substr($hex, $protocolIdPos * 2, 2);
        
        // Ambil Serial Number (2 byte sebelum CRC & Stop bit)
        $serialNum = substr($hex, strlen($hex) - 12, 4);

        // --- A. LOGIN PACKET (01) ---
        if ($protocolId == '01') {
            $terminalId = substr($hex, 8, 16);
            $this->info("🔑 GT06N LOGIN: " . $terminalId);

            $resBody = "0501" . $serialNum;
            $crc = $this->getCRC16($resBody);
            $response = hex2bin("7878" . $resBody . $crc . "0d0a");
            $connection->write($response);
        } 
        
        // --- B. GPS LOCATION (22) ---
        elseif ($protocolId == '22') {
            $this->info("📍 PROCESSING 4G LOCATION DATA...");
            
            // Parsing Koordinat (Offset untuk paket 7979)
            $startOffset = $is4G ? 10 : 6; 
            
            // Data Lokasi pada paket 22:
            // Datetime(6), Sat(1), Lat(4), Lng(4), Speed(1), Course(2)
            $latHex = substr($hex, ($startOffset + 7) * 2, 8);
            $lngHex = substr($hex, ($startOffset + 11) * 2, 8);
            $speedHex = substr($hex, ($startOffset + 15) * 2, 2);
            
            $lat = hexdec($latHex) / 1800000;
            $lng = hexdec($lngHex) / 1800000;
            $speed = hexdec($speedHex);

            // Mapping Device (Potong ID jika ada 0 di depan)
            $terminalId = substr($hex, 8, 16);
            $device = DB::table('devices')
                ->where('factory_id', 'LIKE', '%' . substr($terminalId, -12))
                ->first();

            if ($device) {
                // ACC Status (Biasanya di bit status paket heartbeat atau paket lokasi biner)
                $accStatus = ($speed > 5) ? 1 : 0; 
                $this->savePosition($device, $lat, $lng, $speed, $accStatus);
                $this->info("✅ SUCCESS: $device->name Berhasil Update!");
            }
        }
        
        // --- C. HEARTBEAT (13) ---
        elseif ($protocolId == '13') {
            $this->info("💓 HEARTBEAT RECEIVED");
            $response = hex2bin("78780513" . $serialNum . $this->getCRC16("0513".$serialNum) . "0d0a");
            $connection->write($response);
        }
    }

    private function parseTextProtocol($connection, $text)
    {
        $content = substr($text, 1, -1);
        $factoryId = substr($content, 0, 12);
        $cmd = substr($content, 12, 4);
        $data = substr($content, 16);

        $device = DB::table('devices')->where('factory_id', $factoryId)->first();

        if ($cmd == 'BP05') {
            $connection->write("(" . $factoryId . "AP05)");
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

    private function savePosition($device, $lat, $lng, $speed, $acc)
    {
        $speed = ($speed < 3) ? 0 : $speed;
        DB::table('positions')->insert([
            'imei' => $device->imei, 'latitude' => $lat, 'longitude' => $lng,
            'speed' => $speed, 'gps_time' => Carbon::now(), 'created_at' => Carbon::now()
        ]);
        DB::table('devices')->where('imei', $device->imei)->update([
            'acc_status' => $acc, 'last_online' => Carbon::now(), 'updated_at' => Carbon::now()
        ]);
    }

    private function getCRC16($hex) {
        $data = hex2bin($hex);
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x0001) $crc = ($crc >> 1) ^ 0x8408;
                else $crc >>= 1;
            }
        }
        return sprintf('%04x', ~$crc & 0xFFFF);
    }

    private function dmToDecimal($dm) {
        $dotPos = strpos($dm, '.');
        return floatval(substr($dm, 0, $dotPos - 2)) + (floatval(substr($dm, $dotPos - 2)) / 60);
    }
}