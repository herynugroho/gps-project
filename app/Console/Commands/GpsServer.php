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
    protected $description = 'GPS Server Optimized for GT06N 4G with Connection State';

    // Map untuk menyimpan IMEI berdasarkan koneksi
    private $connectionDeviceMap = [];

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 PRIMA GPS SUPER HYBRID SERVER [4G READY] STARTED ON PORT $port");
        $this->info("-------------------------------------------------------");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connId = spl_object_hash($connection);
            
            $connection->on('data', function ($data) use ($connection, $connId) {
                $hex = bin2hex($data);
                $this->info("📥 RAW HEX RECEIVED: " . $hex);
                
                $this->handleIncomingData($connection, $data, $connId);
            });

            $connection->on('close', function() use ($connId) {
                unset($this->connectionDeviceMap[$connId]);
                $this->info("🔌 Connection Closed: " . $connId);
            });
        });

        $loop->run();
    }

    private function handleIncomingData($connection, $raw, $connId)
    {
        $hex = bin2hex($raw);
        $text = trim($raw);

        // 1. PROTOKOL BINER CONCOX (7878 / 7979)
        if (str_starts_with($hex, '7878') || str_starts_with($hex, '7979')) {
            $this->parseBinaryProtocol($connection, $hex, $connId);
        }
        // 2. PROTOKOL TEKS (MODUL LAMA)
        elseif (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $this->parseTextProtocol($connection, $text);
        }
    }

    private function parseBinaryProtocol($connection, $hex, $connId)
    {
        $startBit = substr($hex, 0, 4);
        $is4G = ($startBit === '7979');
        
        // Offset Protokol ID
        $protocolIdPos = $is4G ? 4 : 3;
        $protocolId = substr($hex, $protocolIdPos * 2, 2);
        
        // Ambil Serial Number (2 byte sebelum CRC & Stop bit)
        $serialNum = substr($hex, strlen($hex) - 12, 4);

        // --- A. LOGIN PACKET (01) ---
        if ($protocolId == '01') {
            $terminalId = substr($hex, 8, 16);
            $this->info("🔑 GT06N LOGIN ATTEMPT: " . $terminalId);

            // Cari device berdasarkan Factory ID atau IMEI
            $device = DB::table('devices')
                ->where('factory_id', 'LIKE', '%' . substr($terminalId, -11))
                ->orWhere('imei', 'LIKE', '%' . substr($terminalId, -11))
                ->first();

            if ($device) {
                $this->connectionDeviceMap[$connId] = $device;
                $this->info("✅ Device Authenticated: " . $device->name);
                
                // Response Login (Selalu pakai 7878 untuk handshake)
                $resBody = "0501" . $serialNum;
                $crc = $this->getCRC16($resBody);
                $connection->write(hex2bin("7878" . $resBody . $crc . "0d0a"));
            } else {
                $this->warn("❌ UNKNOWN DEVICE LOGIN: " . $terminalId);
            }
        } 
        
        // --- B. DATA PACKETS (22: Lokasi, 94: Informasi Status) ---
        elseif ($protocolId == '22' || $protocolId == '12' || $protocolId == '94') {
            // Ambil data device yang sudah tersimpan di memori koneksi
            $device = $this->connectionDeviceMap[$connId] ?? null;

            if (!$device) {
                $this->warn("⚠️ Packet Received but no authenticated device on this connection.");
                return;
            }

            if ($protocolId == '94') {
                $this->info("📍 Info Packet (94) for " . $device->name);
                // Jawab ACK khusus 4G (7979)
                $resBody = "000594" . $serialNum;
                $crc = $this->getCRC16($resBody);
                $connection->write(hex2bin("7979" . $resBody . $crc . "0d0a"));
            } 
            else {
                $this->info("📍 Location Packet (" . $protocolId . ") for " . $device->name);
                $startOffset = $is4G ? 5 : 4; 
                $lat = hexdec(substr($hex, ($startOffset + 6) * 2, 8)) / 1800000;
                $lng = hexdec(substr($hex, ($startOffset + 10) * 2, 8)) / 1800000;
                $speed = hexdec(substr($hex, ($startOffset + 14) * 2, 2));

                // Ambil ACC dari bit status
                $statusByte = hexdec(substr($hex, ($startOffset + 24) * 2, 2));
                $accStatus = ($statusByte & 0x02) ? 1 : 0;

                $this->savePosition($device, $lat, $lng, $speed, $accStatus);
                $this->info("✅ Update Success: " . $device->name);
            }
        }
        
        // --- C. HEARTBEAT (13) ---
        elseif ($protocolId == '13') {
            $resBody = "0513" . $serialNum;
            $connection->write(hex2bin("7878" . $resBody . $this->getCRC16($resBody) . "0d0a"));
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
            $this->info("🔑 Text Login: " . $factoryId);
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
        if ($lat == 0 || $lng == 0) return; // Abaikan jika GPS belum lock (koordinat 0)

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