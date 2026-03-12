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
    protected $description = 'Professional Hybrid GPS Server - Prima GPS V5.1';

    private $connectionDeviceMap = [];
    private $connectionBuffer = [];

    // --- Warna Terminal (ANSI) ---
    const C_RST = "\e[0m";
    const C_BLU = "\e[1;34m"; // Biru - GOWA/4G
    const C_YLW = "\e[1;33m"; // Kuning - KOTA/Standard
    const C_GRN = "\e[1;32m"; // Hijau - Sukses Update
    const C_RED = "\e[1;31m"; // Merah - Alert/Disconnect
    const C_CYN = "\e[1;36m"; // Cyan - System

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->line(self::C_CYN . "=======================================================" . self::C_RST);
        $this->line(self::C_CYN . "📡  PRIMA GPS HYBRID SERVER V5.1 - MONITORING STARTED" . self::C_RST);
        $this->line(self::C_CYN . "    Port: $port | Mode: Multi-Protocol (4G & Text)" . self::C_RST);
        $this->line(self::C_CYN . "=======================================================" . self::C_RST);

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connId = spl_object_hash($connection);
            $this->connectionBuffer[$connId] = '';
            
            $connection->on('data', function ($data) use ($connection, $connId) {
                $this->connectionBuffer[$connId] .= bin2hex($data);
                $this->processBuffer($connection, $connId);
            });

            $connection->on('close', function() use ($connId) {
                if (isset($this->connectionDeviceMap[$connId])) {
                    $dev = $this->connectionDeviceMap[$connId];
                    $this->line(self::C_RED . "❌ [" . $dev->name . "] Connection Closed." . self::C_RST);
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

        while (strlen($buffer) >= 4) {
            if (str_starts_with($buffer, '7878') || str_starts_with($buffer, '7979')) {
                $is4G = str_starts_with($buffer, '7979');
                $len = $is4G ? hexdec(substr($buffer, 4, 4)) : hexdec(substr($buffer, 4, 2));
                $totalLenHex = ($len + ($is4G ? 6 : 5)) * 2;
                
                if (strlen($buffer) < $totalLenHex) break;
                
                $packet = substr($buffer, 0, $totalLenHex);
                $this->handleBinaryPacket($connection, $packet, $connId, $is4G);
                $buffer = substr($buffer, $totalLenHex);
            } 
            elseif (str_starts_with($buffer, '28')) {
                $endPos = strpos($buffer, '29');
                if ($endPos === false) break;
                
                $packetHex = substr($buffer, 0, $endPos + 2);
                $this->handleTextPacket($connection, hex2bin($packetHex), $connId);
                $buffer = substr($buffer, $endPos + 2);
            } else {
                $buffer = substr($buffer, 2);
            }
        }
    }

    private function handleBinaryPacket($connection, $hex, $connId, $is4G)
    {
        $protocolIdPos = $is4G ? 4 : 3;
        $protocolId = substr($hex, $protocolIdPos * 2, 2);
        $serialNum = substr($hex, strlen($hex) - 12, 4);

        if ($protocolId == '01') { // LOGIN
            $terminalId = substr($hex, 8, 16);
            $device = $this->findDevice($terminalId);
            
            if ($device) {
                $this->connectionDeviceMap[$connId] = $device;
                $this->line(self::C_BLU . "🔑 [4G] LOGIN: " . str_pad($device->name, 10) . " (ID: $terminalId)" . self::C_RST);
                $resBody = "0501" . $serialNum;
                $connection->write(hex2bin("7878" . $resBody . $this->getCRC16($resBody) . "0d0a"));
                $this->updateStatus($device, 0);
            }
        } else {
            $device = $this->connectionDeviceMap[$connId] ?? null;
            if (!$device) return;

            if ($protocolId == '94') { // STATUS/INFO
                $this->line(self::C_CYN . "📊 [4G] INFO:  " . $device->name . " (Awaiting GPS Fix...)" . self::C_RST);
                $resBody = "000594" . $serialNum;
                $connection->write(hex2bin("7979" . $resBody . $this->getCRC16($resBody) . "0d0a"));
                $this->updateStatus($device, $device->acc_status);
            } 
            elseif ($protocolId == '13') { // HEARTBEAT
                $resBody = "0513" . $serialNum;
                $connection->write(hex2bin("7878" . $resBody . $this->getCRC16($resBody) . "0d0a"));
                $this->updateStatus($device, $device->acc_status);
            }
            elseif ($protocolId == '22' || $protocolId == '12') { // LOCATION
                $latByte = $is4G ? 12 : 11; $lngByte = $is4G ? 16 : 15; $spdByte = $is4G ? 20 : 19;
                $latVal = hexdec(substr($hex, $latByte * 2, 8));
                $lngVal = hexdec(substr($hex, $lngByte * 2, 8));
                
                if ($latVal == 0 || $lngVal == 0) {
                    $this->line(self::C_CYN . "📡 [4G] NO FIX: " . $device->name . " (Satellite searching)" . self::C_RST);
                    $this->updateStatus($device, $device->acc_status);
                    return;
                }

                $lat = $latVal / 1800000;
                $lng = $lngVal / 1800000;
                $speed = hexdec(substr($hex, $spdByte * 2, 2));
                
                $this->savePosition($device, $lat, $lng, $speed, ($speed > 2 ? 1 : 0), "4G");
            } else {
                $this->line(self::C_RED . "❓ [4G] UNKNOWN PROTOCOL: $protocolId from " . $device->name . self::C_RST);
            }
        }
    }

    private function handleTextPacket($connection, $text, $connId)
    {
        if (preg_match('/\(([^)]+)\)/', $text, $match)) {
            $content = $match[1];
            $idInPacket = substr($content, 0, 12);
            $cmd = substr($content, 12, 4);
            $data = substr($content, 16);
            
            $device = $this->connectionDeviceMap[$connId] ?? $this->findDevice($idInPacket);

            if ($device) {
                $this->connectionDeviceMap[$connId] = $device;
                
                if ($cmd == 'BP05') { // LOGIN
                    $connection->write("(" . $idInPacket . "AP05)");
                    $this->line(self::C_YLW . "📝 [TXT] LOGIN: " . str_pad($device->name, 10) . " (ID: $idInPacket)" . self::C_RST);
                    $this->updateStatus($device, 0);
                } 
                elseif (($cmd == 'BR00' || $cmd == 'BP04')) { // LOCATION
                    if (preg_match('/[AV](\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $m)) {
                        $lat = $this->dmToDecimal($m[1]) * ($m[2] == 'S' ? -1 : 1);
                        $lng = $this->dmToDecimal($m[3]) * ($m[4] == 'W' ? -1 : 1);
                        $speed = floatval($m[5]) * 1.852;
                        $this->savePosition($device, $lat, $lng, $speed, ($speed > 5 ? 1 : 0), "TXT");
                        
                        $resCmd = ($cmd == 'BR00') ? "AR00" : "AP04";
                        $connection->write("(" . $idInPacket . $resCmd . ")");
                    }
                }
                elseif ($cmd == 'BP00' || str_contains($content, 'HSO')) { // HEARTBEAT
                    $connection->write("(" . $idInPacket . "AP00)");
                    $this->updateStatus($device, $device->acc_status);
                }
            } else {
                $this->line(self::C_RED . "⚠️  UNKNOWN DEVICE ID: $idInPacket" . self::C_RST);
            }
        }
    }

    private function findDevice($id) {
        $shortId = substr($id, -8);
        return DB::table('devices')
            ->where('factory_id', 'LIKE', '%' . $shortId)
            ->orWhere('imei', 'LIKE', '%' . $shortId)
            ->first();
    }

    private function savePosition($device, $lat, $lng, $speed, $acc, $type) {
        $speed = ($speed < 3) ? 0 : $speed;
        
        DB::table('positions')->insert([
            'imei' => $device->imei, 'latitude' => $lat, 'longitude' => $lng, 
            'speed' => $speed, 'gps_time' => Carbon::now(), 'created_at' => Carbon::now()
        ]);
        
        $this->updateStatus($device, $acc);
        
        $prefix = ($type == "TXT") ? self::C_YLW . "📍 [TXT]" : self::C_BLU . "📍 [4G] ";
        $this->line($prefix . " UPDATE: " . str_pad($device->name, 8) . " | SPEED: " . str_pad(round($speed), 3) . " | POS: " . round($lat,4) . "," . round($lng,4) . self::C_RST);
    }

    private function updateStatus($device, $acc) {
        DB::table('devices')->where('imei', $device->imei)->update([
            'acc_status' => $acc, 'last_online' => Carbon::now(), 'updated_at' => Carbon::now()
        ]);
    }

    private function getCRC16($hex) {
        $data = hex2bin($hex); $crc = 0xFFFF;
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