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
    protected $description = 'Hybrid GPS Server with Advanced Multi-Device Logging and Raw Hex Debug';

    private $connectionDeviceMap = [];
    private $connectionBuffer = [];

    // --- Warna ANSI untuk Terminal ---
    const CLR_RESET = "\e[0m";
    const CLR_GOWA  = "\e[1;36m"; // Cyan untuk GOWA (4G)
    const CLR_GT06N = "\e[1;36m"; // Cyan untuk GT06N (Umum)
    const CLR_KOTA  = "\e[1;33m"; // Kuning untuk KOTA (Standard)
    const CLR_SUCC  = "\e[1;32m"; // Hijau untuk Sukses Update
    const CLR_WARN  = "\e[1;31m"; // Merah untuk Peringatan
    const CLR_SYS   = "\e[1;34m"; // Biru untuk Sistem

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->line(self::CLR_SYS . "=======================================================" . self::CLR_RESET);
        $this->line(self::CLR_SYS . "📡  PRIMA GPS HYBRID SERVER V6.1 - FIXED" . self::CLR_RESET);
        $this->line(self::CLR_SYS . "    Port: $port | Mode: Auto-Switch (4G/2G/Text)" . self::CLR_RESET);
        $this->line(self::CLR_SYS . "=======================================================" . self::CLR_RESET);

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connId = spl_object_hash($connection);
            $this->connectionBuffer[$connId] = '';
            
            $connection->on('data', function ($data) use ($connection, $connId) {
                $hex = bin2hex($data);
                $this->connectionBuffer[$connId] .= $hex;
                $this->processBuffer($connection, $connId);
            });

            $connection->on('close', function() use ($connId) {
                if (isset($this->connectionDeviceMap[$connId])) {
                    $device = $this->connectionDeviceMap[$connId];
                    $this->line(self::CLR_WARN . "🔌 [DISCONNECT] " . $device->name . self::CLR_RESET);
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
                // --- GT06N / BINARY PROTOCOL ---
                $is4G = str_starts_with($buffer, '7979');
                $len = $is4G ? hexdec(substr($buffer, 4, 4)) : hexdec(substr($buffer, 4, 2));
                $totalLenHex = ($len + ($is4G ? 6 : 5)) * 2;

                if (strlen($buffer) < $totalLenHex) break;
                
                $packet = substr($buffer, 0, $totalLenHex);
                $this->handleBinaryPacket($connection, $packet, $connId, $is4G);
                $buffer = substr($buffer, $totalLenHex);
            } 
            elseif (str_starts_with($buffer, '28')) { // ASCII '('
                // --- TEXT / STANDARD PROTOCOL ---
                $endPos = strpos($buffer, '29'); // ASCII ')'
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
        
        $device = $this->connectionDeviceMap[$connId] ?? null;
        $deviceName = $device ? $device->name : "Logging In...";
        $color = ($device && str_contains($device->name, 'GOWA')) ? self::CLR_GOWA : self::CLR_GT06N;

        // Log khusus untuk GT06N agar mudah dianalisa
        $this->line($color . "📦 [BIN-" . ($is4G ? "4G" : "2G") . "] " . str_pad($deviceName, 8) . " | Prot: $protocolId" . self::CLR_RESET);
        $this->line("   RAW: " . $hex);

        if ($protocolId == '01') { // LOGIN
            $terminalId = substr($hex, 8, 16);
            $device = $this->findDevice($terminalId);
            if ($device) {
                $this->connectionDeviceMap[$connId] = $device;
                $this->line(self::CLR_SUCC . "   ✅ Login OK: " . $device->name . self::CLR_RESET);
                
                $resBody = "0501" . $serialNum;
                $response = "7878" . $resBody . $this->getCRC16($resBody) . "0d0a";
                $connection->write(hex2bin($response));
                $this->updateStatus($device, 0);
            } else {
                $this->line(self::CLR_WARN . "   ⚠️ Unknown ID: $terminalId" . self::CLR_RESET);
            }
        } else {
            if (!$device) return;

            if ($protocolId == '94') { // INFO PACKET (Respon harus sesuai tipe start bit)
                $resBody = "000594" . $serialNum;
                $startBit = $is4G ? "7979" : "7878";
                $response = $startBit . $resBody . $this->getCRC16($resBody) . "0d0a";
                $connection->write(hex2bin($response));
                $this->updateStatus($device, $device->acc_status);
                $this->line("   📤 Responded to Info Packet (94)");
            } 
            elseif ($protocolId == '13') { // HEARTBEAT
                $resBody = "0513" . $serialNum;
                $response = "7878" . $resBody . $this->getCRC16($resBody) . "0d0a";
                $connection->write(hex2bin($response));
                $this->updateStatus($device, $device->acc_status);
            }
            elseif ($protocolId == '22' || $protocolId == '12') { // LOCATION
                $latByte = $is4G ? 12 : 11; 
                $lngByte = $is4G ? 16 : 15; 
                $spdByte = $is4G ? 20 : 19;
                
                $latVal = hexdec(substr($hex, $latByte * 2, 8));
                $lngVal = hexdec(substr($hex, $lngByte * 2, 8));
                
                if ($latVal == 0 || $lngVal == 0) {
                    $this->line("   📡 GPS searching...");
                    return;
                }

                $lat = $latVal / 1800000;
                $lng = $lngVal / 1800000;
                $speed = hexdec(substr($hex, $spdByte * 2, 2));
                $statusByte = hexdec(substr($hex, ($latByte + 13) * 2, 2));
                $acc = ($statusByte & 0x02) ? 1 : 0;

                $this->savePosition($device, $lat, $lng, $speed, $acc);
                $this->line(self::CLR_SUCC . "   📍 UPDATE: $lat, $lng | SPD: $speed | ACC: " . ($acc ? 'ON':'OFF') . self::CLR_RESET);
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
                $this->line(self::CLR_KOTA . "📝 [TEXT] " . str_pad($device->name, 8) . " | CMD: $cmd" . self::CLR_RESET);

                if ($cmd == 'BP05') {
                    $connection->write("(" . $idInPacket . "AP05)");
                    $this->updateStatus($device, 0);
                } 
                elseif (($cmd == 'BR00' || $cmd == 'BP04')) {
                    if (preg_match('/[AV](\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $m)) {
                        $lat = $this->dmToDecimal($m[1]) * ($m[2] == 'S' ? -1 : 1);
                        $lng = $this->dmToDecimal($m[3]) * ($m[4] == 'W' ? -1 : 1);
                        $speed = floatval($m[5]) * 1.852;
                        
                        $this->savePosition($device, $lat, $lng, $speed, ($speed > 5 ? 1 : 0));
                        $this->line(self::CLR_SUCC . "   📍 UPDATE: $lat, $lng | SPD: " . round($speed) . self::CLR_RESET);
                        
                        $connection->write("(" . $idInPacket . (($cmd == 'BR00') ? "AR00" : "AP04") . ")");
                    }
                }
                elseif ($cmd == 'BP00' || str_contains($content, 'HSO')) {
                    $connection->write("(" . $idInPacket . "AP00)");
                    $this->updateStatus($device, $device->acc_status);
                }
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

    private function savePosition($device, $lat, $lng, $speed, $acc) {
        if ($lat == 0 || $lng == 0) return;
        DB::table('positions')->insert([
            'imei' => $device->imei,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed' => $speed,
            'gps_time' => Carbon::now(),
            'created_at' => Carbon::now()
        ]);
        $this->updateStatus($device, $acc);
    }

    private function updateStatus($device, $acc) {
        DB::table('devices')->where('imei', $device->imei)->update([
            'acc_status' => $acc,
            'last_online' => Carbon::now(),
            'updated_at' => Carbon::now()
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