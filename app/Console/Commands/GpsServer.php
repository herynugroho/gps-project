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
    protected $description = 'GPS Server Optimized for GT06N 4G with Connection Buffering';

    // Map untuk menyimpan objek device berdasarkan ID koneksi
    private $connectionDeviceMap = [];
    // Buffer untuk menangani paket data yang terpotong
    private $connectionBuffer = [];

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 PRIMA GPS SUPER HYBRID SERVER [4G READY] STARTED ON PORT $port");
        $this->info("-------------------------------------------------------");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connId = spl_object_hash($connection);
            $this->connectionBuffer[$connId] = '';
            
            $connection->on('data', function ($data) use ($connection, $connId) {
                // Tambahkan data baru ke buffer hex
                $this->connectionBuffer[$connId] .= bin2hex($data);
                $this->processBuffer($connection, $connId);
            });

            $connection->on('close', function() use ($connId) {
                if (isset($this->connectionDeviceMap[$connId])) {
                    $this->info("🔌 Connection Closed: " . $this->connectionDeviceMap[$connId]->name);
                }
                unset($this->connectionDeviceMap[$connId]);
                unset($this->connectionBuffer[$connId]);
            });
        });

        $loop->run();
    }

    private function processBuffer($connection, $connId)
    {
        $buffer = &$this->connectionBuffer[$connId];

        // Cari start bit protokol (7878, 7979, atau teks '(' )
        while (strlen($buffer) >= 4) {
            if (str_starts_with($buffer, '7878')) {
                // Protokol 2G (GT02 / GT06N 2G)
                $len = hexdec(substr($buffer, 4, 2));
                $totalLenHex = ($len + 5) * 2; // Start(2) + Len(1) + Serial(2) + CRC(2) + Stop(2) -> dikali 2 untuk hex
                if (strlen($buffer) < $totalLenHex) break; // Tunggu data lengkap

                $packet = substr($buffer, 0, $totalLenHex);
                $this->handleBinaryPacket($connection, $packet, $connId, false);
                $buffer = substr($buffer, $totalLenHex);
            } 
            elseif (str_starts_with($buffer, '7979')) {
                // Protokol 4G (GT06N 4G)
                if (strlen($buffer) < 8) break;
                $len = hexdec(substr($buffer, 4, 4));
                $totalLenHex = ($len + 6) * 2; // Start(2) + Len(2) + Serial(2) + CRC(2) + Stop(2)
                if (strlen($buffer) < $totalLenHex) break;

                $packet = substr($buffer, 0, $totalLenHex);
                $this->handleBinaryPacket($connection, $packet, $connId, true);
                $buffer = substr($buffer, $totalLenHex);
            }
            elseif (str_starts_with($buffer, '28')) { // ASCII '('
                $endPos = strpos($buffer, '29'); // ASCII ')'
                if ($endPos === false) break;
                
                $packetHex = substr($buffer, 0, $endPos + 2);
                $this->handleTextPacket($connection, hex2bin($packetHex));
                $buffer = substr($buffer, $endPos + 2);
            }
            else {
                // Buang data sampah di depan jika tidak sesuai start bit
                $buffer = substr($buffer, 2);
            }
        }
    }

    private function handleBinaryPacket($connection, $hex, $connId, $is4G)
    {
        $this->info("📥 BINARY RECEIVED (" . ($is4G ? "4G" : "2G") . "): " . $hex);
        
        $protocolIdPos = $is4G ? 4 : 3;
        $protocolId = substr($hex, $protocolIdPos * 2, 2);
        $serialNum = substr($hex, strlen($hex) - 12, 4);

        // --- LOGIN (01) ---
        if ($protocolId == '01') {
            $terminalId = substr($hex, 8, 16);
            $device = DB::table('devices')
                ->where('factory_id', 'LIKE', '%' . substr($terminalId, -11))
                ->orWhere('imei', 'LIKE', '%' . substr($terminalId, -11))
                ->first();

            if ($device) {
                $this->connectionDeviceMap[$connId] = $device;
                $this->info("✅ Device Authenticated: " . $device->name);
                $resBody = "0501" . $serialNum;
                $connection->write(hex2bin("7878" . $resBody . $this->getCRC16($resBody) . "0d0a"));
                $this->updateDeviceStatus($device, 0);
            }
        } 
        // --- DATA (22, 12, 94, 13) ---
        else {
            $device = $this->connectionDeviceMap[$connId] ?? null;
            if (!$device) return;

            if ($protocolId == '94') {
                $this->info("📊 Info Packet (94) for " . $device->name);
                $resBody = "000594" . $serialNum;
                $startBit = $is4G ? "7979" : "7878";
                $connection->write(hex2bin($startBit . $resBody . $this->getCRC16($resBody) . "0d0a"));
                $this->updateDeviceStatus($device, $device->acc_status);
            } 
            elseif ($protocolId == '13') {
                $resBody = "0513" . $serialNum;
                $connection->write(hex2bin("7878" . $resBody . $this->getCRC16($resBody) . "0d0a"));
                $this->updateDeviceStatus($device, $device->acc_status);
            }
            elseif ($protocolId == '22' || $protocolId == '12') {
                $this->info("📍 Location Packet Received for " . $device->name);
                
                // Koreksi Byte Offset:
                // 2G: Start(2), Len(1), Prot(1), Time(6), Sat(1), Lat(4), Lng(4), Spd(1)...
                //     Lat starts at byte 11 (index 22)
                // 4G: Start(2), Len(2), Prot(1), Time(6), Sat(1), Lat(4), Lng(4), Spd(1)...
                //     Lat starts at byte 12 (index 24)
                $latByte = $is4G ? 12 : 11;
                $lngByte = $is4G ? 16 : 15;
                $spdByte = $is4G ? 20 : 19;

                $lat = hexdec(substr($hex, $latByte * 2, 8)) / 1800000;
                $lng = hexdec(substr($hex, $lngByte * 2, 8)) / 1800000;
                $speed = hexdec(substr($hex, $spdByte * 2, 2));

                // Deteksi ACC: Sederhana via speed atau status bit jika tersedia
                $accStatus = ($speed > 2) ? 1 : 0;

                $this->savePosition($device, $lat, $lng, $speed, $accStatus);
                $this->info("✅ Update Success: " . $device->name . " ($lat, $lng)");
            }
        }
    }

    private function handleTextPacket($connection, $text)
    {
        $this->info("📥 TEXT RECEIVED: " . $text);
        if (preg_match('/\(([^)]+)\)/', $text, $match)) {
            $content = $match[1];
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
    }

    private function savePosition($device, $lat, $lng, $speed, $acc)
    {
        if ($lat == 0 || $lng == 0 || abs($lat) > 90 || abs($lng) > 180) return;

        $speed = ($speed < 3) ? 0 : $speed;
        DB::table('positions')->insert([
            'imei' => $device->imei, 'latitude' => $lat, 'longitude' => $lng,
            'speed' => $speed, 'gps_time' => Carbon::now(), 'created_at' => Carbon::now()
        ]);
        
        $this->updateDeviceStatus($device, $acc);
    }

    private function updateDeviceStatus($device, $acc)
    {
        DB::table('devices')->where('imei', $device->imei)->update([
            'acc_status' => $acc,
            'last_online' => Carbon::now(),
            'updated_at' => Carbon::now()
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