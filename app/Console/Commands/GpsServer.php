<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GpsServer extends Command
{
    // Port 5022 untuk GPS, Port 5023 untuk Jembatan Kontrol dari Dashboard
    protected $signature = 'gps:server {port=5022} {control_port=5023}';
    protected $description = 'Hybrid GPS Server V7.0 - Active Command Bridge Enabled';

    private $connectionDeviceMap = []; // Menyimpan IMEI => Objek Koneksi Aktif
    private $connectionBuffer = [];

    // --- Warna ANSI untuk Terminal ---
    const CLR_RESET = "\e[0m";
    const CLR_GOWA  = "\e[1;36m"; // Cyan
    const CLR_GT06N = "\e[1;36m"; 
    const CLR_KOTA  = "\e[1;33m"; // Kuning
    const CLR_SUCC  = "\e[1;32m"; // Hijau
    const CLR_WARN  = "\e[1;31m"; // Merah
    const CLR_SYS   = "\e[1;34m"; // Biru

    public function handle()
    {
        $port = $this->argument('port');
        $controlPort = $this->argument('control_port');
        $loop = \React\EventLoop\Factory::create();

        // ---------------------------------------------------------
        // 1. SERVER SOCKET UNTUK PENERIMA DATA GPS (PORT 5022)
        // ---------------------------------------------------------
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);
        $this->line(self::CLR_SYS . "📡 GPS SOCKET RECEIVER STARTED ON PORT $port" . self::CLR_RST);

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connId = spl_object_hash($connection);
            $this->connectionBuffer[$connId] = '';
            
            $connection->on('data', function ($data) use ($connection, $connId) {
                $hex = bin2hex($data);
                $this->connectionBuffer[$connId] .= $hex;
                $this->processBuffer($connection, $connId);
            });

            $connection->on('close', function() use ($connId) {
                // Bersihkan map saat koneksi putus
                foreach ($this->connectionDeviceMap as $imei => $conn) {
                    if (spl_object_hash($conn) === $connId) {
                        $this->line(self::CLR_WARN . "🔌 [DISCONNECT] IMEI: $imei" . self::CLR_RST);
                        unset($this->connectionDeviceMap[$imei]);
                        break;
                    }
                }
                unset($this->connectionBuffer[$connId]);
            });
        });

        // ---------------------------------------------------------
        // 2. SERVER HTTP UNTUK JEMBATAN KONTROL (PORT 5023)
        // ---------------------------------------------------------
        $http = new HttpServer($loop, function (ServerRequestInterface $request) {
            $path = $request->getUri()->getPath();
            
            if ($path === '/send-command') {
                $params = $request->getQueryParams();
                $imei = $params['imei'] ?? '';
                $cmdText = $params['command'] ?? '';

                if (isset($this->connectionDeviceMap[$imei])) {
                    $conn = $this->connectionDeviceMap[$imei];
                    $binaryPacket = $this->buildProtocol80($cmdText);
                    
                    $conn->write(hex2bin($binaryPacket));
                    
                    $this->line(self::CLR_SYS . "📤 [GPRS CMD] Sent to $imei: $cmdText" . self::CLR_RST);
                    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                        'status' => 'success', 
                        'msg' => 'Command sent to device via GPRS connection.'
                    ]));
                }
                
                return new Response(404, ['Content-Type' => 'application/json'], json_encode([
                    'status' => 'error', 
                    'msg' => 'Device is offline or not registered in current session.'
                ]));
            }
            return new Response(404, [], 'Not Found');
        });

        $httpSocket = new SocketServer("127.0.0.1:$controlPort", [], $loop);
        $http->listen($httpSocket);
        $this->line(self::CLR_SYS . "🌐 CONTROL BRIDGE ACTIVE ON PORT $controlPort" . self::CLR_RST);
        $this->line("-------------------------------------------------------");

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
                $this->handleBinaryPacket($connection, substr($buffer, 0, $totalLenHex), $connId, $is4G);
                $buffer = substr($buffer, $totalLenHex);
            } 
            elseif (str_starts_with($buffer, '28')) {
                $endPos = strpos($buffer, '29');
                if ($endPos === false) break;
                $this->handleTextPacket($connection, hex2bin(substr($buffer, 0, $endPos + 2)), $connId);
                $buffer = substr($buffer, $endPos + 2);
            } else { $buffer = substr($buffer, 2); }
        }
    }

    private function handleBinaryPacket($connection, $hex, $connId, $is4G)
    {
        $protocolIdPos = $is4G ? 4 : 3;
        $protocolId = substr($hex, $protocolIdPos * 2, 2);
        $serialNum = substr($hex, strlen($hex) - 12, 4);
        
        // Cari IMEI di memori atau database
        $currentImei = null;
        foreach($this->connectionDeviceMap as $imei => $conn) {
            if (spl_object_hash($conn) === $connId) { $currentImei = $imei; break; }
        }

        if ($protocolId == '01') { // LOGIN
            $terminalId = substr($hex, 8, 16);
            $device = $this->findDevice($terminalId);
            if ($device) {
                $this->connectionDeviceMap[$device->imei] = $connection;
                $this->line(self::CLR_SUCC . "✅ [ONLINE] " . $device->name . " (IMEI: $device->imei)" . self::CLR_RST);
                $connection->write(hex2bin("78780501" . $serialNum . $this->getCRC16("0501".$serialNum) . "0d0a"));
                $this->updateStatus($device, 0);
            }
        } else {
            if (!$currentImei) return;
            $device = DB::table('devices')->where('imei', $currentImei)->first();

            // --- LOKASI (22, 12, 16, a0) ---
            if (in_array($protocolId, ['22', '12', '16', 'a0'])) {
                $latByte = ($is4G && $protocolId == '22') ? 12 : 11;
                if ($protocolId == '16') $latByte = $is4G ? 13 : 12;

                $timeHex = substr($hex, ($latByte - 7) * 2, 12);
                $gpsTime = $this->parseGpsTime($timeHex);
                $latVal = hexdec(substr($hex, $latByte * 2, 8));
                $lngVal = hexdec(substr($hex, ($latByte + 4) * 2, 8));

                if ($latVal > 0 && $lngVal > 0) {
                    $lat = $latVal / 1800000; $lng = $lngVal / 1800000;
                    $speed = hexdec(substr($hex, ($latByte + 8) * 2, 2));
                    $courseStatus = hexdec(substr($hex, ($latByte + 9) * 2, 4));
                    if (!($courseStatus & 0x0400)) $lat = -$lat;
                    if ($courseStatus & 0x0800) $lng = -$lng;
                    $statusByte = hexdec(substr($hex, ($latByte + 13) * 2, 2));
                    $acc = ($statusByte & 0x02) ? 1 : 0;

                    $this->savePosition($device, $lat, $lng, $speed, $acc, $gpsTime, "BIN");
                }
            }
            // --- RESPONSE UNTUK HEARTBEAT & INFO ---
            elseif ($protocolId == '94') {
                $res = ($is4G ? "79790005" : "787805") . "94" . $serialNum;
                $connection->write(hex2bin($res . $this->getCRC16(substr($res, 4)) . "0d0a"));
                $this->updateStatus($device, $device->acc_status);
            }
            elseif ($protocolId == '13') {
                $connection->write(hex2bin("78780513" . $serialNum . $this->getCRC16("0513".$serialNum) . "0d0a"));
                $this->updateStatus($device, $device->acc_status);
            }
        }
    }

    private function buildProtocol80($command) {
        $serverFlag = "00000000"; 
        $cmdHex = bin2hex($command);
        $cmdLen = strlen($command);
        
        // Format: 80 [Len+4] [Flag] [Cmd] [Serial]
        $content = "80" . sprintf("%02x", $cmdLen + 4) . $serverFlag . $cmdHex . "0001";
        $totalLen = strlen($content) / 2;
        
        // Start bit 78 78
        $packet = "7878" . sprintf("%02x", $totalLen) . $content . $this->getCRC16(sprintf("%02x", $totalLen) . $content) . "0d0a";
        return $packet;
    }

    private function handleTextPacket($connection, $text, $connId) {
        if (preg_match('/\(([^)]+)\)/', $text, $match)) {
            $content = $match[1]; $idInPacket = substr($content, 0, 12);
            $cmd = substr($content, 12, 4); $data = substr($content, 16);
            $device = $this->findDevice($idInPacket);
            if ($device) {
                $this->connectionDeviceMap[$device->imei] = $connection;
                if ($cmd == 'BP05') { $connection->write("(" . $idInPacket . "AP05)"); $this->updateStatus($device, 0); } 
                elseif (($cmd == 'BR00' || $cmd == 'BP04')) {
                    if (preg_match('/[AV](\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $m)) {
                        $lat = $this->dmToDecimal($m[1]) * ($m[2] == 'S' ? -1 : 1);
                        $lng = $this->dmToDecimal($m[3]) * ($m[4] == 'W' ? -1 : 1);
                        $this->savePosition($device, $lat, $lng, floatval($m[5])*1.852, 1, Carbon::now(), "TXT");
                        $connection->write("(" . $idInPacket . (($cmd == 'BR00') ? "AR00" : "AP04") . ")");
                    }
                }
            }
        }
    }

    private function parseGpsTime($hex) {
        $y = hexdec(substr($hex,0,2)); $m = hexdec(substr($hex,2,2)); $d = hexdec(substr($hex,4,2));
        $h = hexdec(substr($hex,6,2)); $i = hexdec(substr($hex,8,2)); $s = hexdec(substr($hex,10,2));
        return Carbon::create(2000+$y, $m, $d, $h, $i, $s, 'UTC');
    }

    private function findDevice($id) {
        $shortId = substr($id, -8);
        return DB::table('devices')->where('factory_id', 'LIKE', '%' . $shortId)->orWhere('imei', 'LIKE', '%' . $shortId)->first();
    }

    private function savePosition($device, $lat, $lng, $speed, $acc, $time, $source) {
        DB::table('positions')->insert(['imei'=>$device->imei,'latitude'=>$lat,'longitude'=>$lng,'speed'=>$speed,'gps_time'=>$time,'created_at'=>Carbon::now()]);
        $this->updateStatus($device, $acc);
        $this->line(self::CLR_SUCC . "   📍 [$source] UPDATE: $device->name ($lat, $lng)" . self::CLR_RST);
    }

    private function updateStatus($device, $acc) {
        DB::table('devices')->where('imei', $device->imei)->update(['acc_status'=>$acc,'last_online'=>Carbon::now(),'updated_at'=>Carbon::now()]);
    }

    private function getCRC16($hex) {
        $data = hex2bin($hex); $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x0001) $crc = ($crc >> 1) ^ 0x8408; else $crc >>= 1;
            }
        }
        return sprintf('%04x', ~$crc & 0xFFFF);
    }

    private function dmToDecimal($dm) {
        $dotPos = strpos($dm, '.');
        return floatval(substr($dm, 0, $dotPos - 2)) + (floatval(substr($dm, $dotPos - 2)) / 60);
    }
}