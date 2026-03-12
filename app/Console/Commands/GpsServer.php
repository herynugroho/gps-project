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

        // --- 1. PROTOKOL TEKS (MODUL LAMA) ---
        if (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $this->parseTextProtocol($connection, $text);
        } 
        // --- 2. PROTOKOL BINER CONCOX (GT06N 4G) ---
        elseif (str_starts_with($hex, '7878')) {
            $this->parseBinaryProtocol($connection, $hex);
        }
    }

    private function parseBinaryProtocol($connection, $hex)
    {
        $protocolId = substr($hex, 6, 2);
        $serialNum = substr($hex, strlen($hex) - 12, 4);

        // ID 01: Login Packet
        if ($protocolId == '01') {
            $terminalId = substr($hex, 8, 16); 
            $this->info("🔑 GT06N Login Attempt: $terminalId");
            
            // Response format: 78 78 [Length] [Protocol] [Serial] [CRC] 0D 0A
            $resBody = "0501" . $serialNum;
            $crc = $this->getCRC16($resBody);
            $response = hex2bin("7878" . $resBody . $crc . "0d0a");
            
            $connection->write($response);
        } 
        
        // ID 22 atau 12: GPS Location Packet
        elseif ($protocolId == '22' || $protocolId == '12') {
            $latHex = substr($hex, 22, 8);
            $lngHex = substr($hex, 30, 8);
            $speedHex = substr($hex, 38, 2);
            $courseStatus = substr($hex, 40, 4);

            $lat = hexdec($latHex) / 1800000;
            $lng = hexdec($lngHex) / 1800000;
            $speed = hexdec($speedHex);
            
            // ACC Status dari bit status (biasanya bit ke-15 di paket ini)
            $accStatus = (hexdec(substr($hex, 60, 2)) & 0x02) ? 1 : 0;

            // Cari device (kita buat fleksibel untuk mencocokkan ID di log)
            $terminalId = substr($hex, 8, 16);
            $device = DB::table('devices')
                ->where('factory_id', $terminalId)
                ->orWhere('imei', 'LIKE', '%' . substr($terminalId, -15))
                ->first();

            if ($device) {
                $this->savePosition($device, $lat, $lng, $speed, $accStatus);
                $this->info("📍 GT06N Update: $device->name | Speed: $speed km/h");
            }
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
            $imei = substr($data, 0, 15);
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

    // Fungsi Kalkulasi CRC16 X25/ITU untuk Concox
    private function getCRC16($hex) {
        $data = hex2bin($hex);
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x0001) {
                    $crc = ($crc >> 1) ^ 0x8408;
                } else {
                    $crc >>= 1;
                }
            }
        }
        return sprintf('%04x', ~$crc & 0xFFFF);
    }

    private function dmToDecimal($dm) {
        $dotPos = strpos($dm, '.');
        return floatval(substr($dm, 0, $dotPos - 2)) + (floatval(substr($dm, $dotPos - 2)) / 60);
    }
}