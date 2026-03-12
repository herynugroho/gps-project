<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GpsServer extends Command
{
    protected $signature = 'gps:server {port=5022} {control_port=5023}';
    protected $description = 'Hybrid GPS Server V7.1 - Visible Command Replies';

    private $connectionDeviceMap = []; 
    private $connectionBuffer = [];

    const CLR_RST   = "\e[0m";
    const CLR_GOWA  = "\e[1;36m"; 
    const CLR_SUCC  = "\e[1;32m"; 
    const CLR_REPLY = "\e[1;32;40m"; // Hijau dengan background hitam untuk balasan
    const CLR_WARN  = "\e[1;31m"; 
    const CLR_SYS   = "\e[1;34m"; 

    public function handle()
    {
        $port = $this->argument('port');
        $controlPort = $this->argument('control_port');
        $loop = \React\EventLoop\Factory::create();

        // 1. GPS RECEIVER (5022)
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);
        $this->line(self::CLR_SYS . "📡 GPS SOCKET RECEIVER STARTED ON PORT $port" . self::CLR_RST);

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connId = spl_object_hash($connection);
            $this->connectionBuffer[$connId] = '';
            
            $connection->on('data', function ($data) use ($connection, $connId) {
                $this->connectionBuffer[$connId] .= bin2hex($data);
                $this->processBuffer($connection, $connId);
            });

            $connection->on('close', function() use ($connId) {
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

        // 2. TCP BRIDGE (5023)
        $controlSocket = new SocketServer("127.0.0.1:$controlPort", [], $loop);
        $this->line(self::CLR_SYS . "🌐 TCP CONTROL BRIDGE ACTIVE ON PORT $controlPort" . self::CLR_RST);

        $controlSocket->on('connection', function (ConnectionInterface $connection) {
            $connection->on('data', function ($data) use ($connection) {
                $payload = trim($data);
                if (str_contains($payload, '|')) {
                    [$imei, $cmdText] = explode('|', $payload, 2);
                    if (isset($this->connectionDeviceMap[$imei])) {
                        $gpsConn = $this->connectionDeviceMap[$imei];
                        $gpsConn->write(hex2bin($this->buildProtocol80($cmdText)));
                        $this->line(self::CLR_SYS . "📤 [GPRS CMD] Sent to $imei: $cmdText" . self::CLR_RST);
                        $connection->write(json_encode(['status' => 'success']));
                    } else {
                        $connection->write(json_encode(['status' => 'error', 'msg' => 'Offline']));
                    }
                }
                $connection->end();
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
        $protocolId = substr($hex, ($is4G ? 4 : 3) * 2, 2);
        $serialNum = substr($hex, strlen($hex) - 12, 4);
        
        $currentImei = null;
        foreach($this->connectionDeviceMap as $imei => $conn) {
            if (spl_object_hash($conn) === $connId) { $currentImei = $imei; break; }
        }

        // --- LOGIN ---
        if ($protocolId == '01') {
            $terminalId = substr($hex, 8, 16);
            $device = DB::table('devices')->where('factory_id', 'LIKE', '%' . substr($terminalId, -8))->orWhere('imei', 'LIKE', '%' . substr($terminalId, -8))->first();
            if ($device) {
                $this->connectionDeviceMap[$device->imei] = $connection;
                $this->line(self::CLR_SUCC . "✅ [ONLINE] " . $device->name . " ($device->imei)" . self::CLR_RST);
                $connection->write(hex2bin("78780501" . $serialNum . $this->getCRC16("0501".$serialNum) . "0d0a"));
            }
        } 
        // --- BALASAN PERINTAH (Protocol 21 / 15 Hex) ---
        elseif ($protocolId == '21' || $protocolId == '15') {
            $lenContent = hexdec(substr($hex, 8, 2));
            $contentHex = substr($hex, 18, ($lenContent - 4) * 2); // Abaikan Flag 4 byte
            $replyText = hex2bin($contentHex);
            $this->line(self::CLR_REPLY . "📩 [REPLY] From " . ($currentImei ?? 'Unknown') . ": " . $replyText . self::C_RST);
        }
        // --- LOKASI ---
        elseif (in_array($protocolId, ['22', '12', '16', 'a0'])) {
            $latByte = ($is4G && $protocolId == '22') ? 12 : 11;
            if ($protocolId == '16') $latByte = $is4G ? 13 : 12;
            $latVal = hexdec(substr($hex, $latByte * 2, 8));
            $lngVal = hexdec(substr($hex, ($latByte + 4) * 2, 8));
            if ($latVal > 0) {
                $lat = $latVal / 1800000; $lng = $lngVal / 1800000;
                $courseStatus = hexdec(substr($hex, ($latByte + 9) * 2, 4));
                if (!($courseStatus & 0x0400)) $lat = -$lat;
                if ($courseStatus & 0x0800) $lng = -$lng;
                $statusByte = hexdec(substr($hex, ($latByte + 13) * 2, 2));
                $acc = ($statusByte & 0x02) ? 1 : 0;
                
                $device = DB::table('devices')->where('imei', $currentImei)->first();
                if($device) {
                    DB::table('positions')->insert(['imei'=>$device->imei,'latitude'=>$lat,'longitude'=>$lng,'speed'=>hexdec(substr($hex, ($latByte+8)*2, 2)),'gps_time'=>Carbon::now(),'created_at'=>Carbon::now()]);
                    DB::table('devices')->where('imei', $device->imei)->update(['acc_status'=>$acc,'last_online'=>Carbon::now()]);
                    $this->line(self::CLR_SUCC . "   📍 UPDATE: $device->name ($lat, $lng) ACC: ".($acc?'ON':'OFF') . self::CLR_RST);
                }
            }
        }
        // --- HEARTBEAT & INFO ---
        elseif ($protocolId == '94') {
            $res = ($is4G ? "79790005" : "787805") . "94" . $serialNum;
            $connection->write(hex2bin($res . $this->getCRC16(substr($res, 4)) . "0d0a"));
        }
        elseif ($protocolId == '13') {
            $connection->write(hex2bin("78780513" . $serialNum . $this->getCRC16("0513".$serialNum) . "0d0a"));
        }
    }

    private function buildProtocol80($command) {
        $cmdHex = bin2hex($command);
        $content = "80" . sprintf("%02x", strlen($command) + 4) . "00000000" . $cmdHex . "0001";
        return "7878" . sprintf("%02x", strlen($content)/2) . $content . $this->getCRC16(sprintf("%02x", strlen($content)/2) . $content) . "0d0a";
    }

    private function handleTextPacket($connection, $text, $connId) {
        if (preg_match('/\(([^)]+)\)/', $text, $match)) {
            $content = $match[1]; $idInPacket = substr($content, 0, 12);
            $cmd = substr($content, 12, 4);
            $device = DB::table('devices')->where('factory_id', 'LIKE', '%' . substr($idInPacket, -8))->first();
            if ($device) {
                $this->connectionDeviceMap[$device->imei] = $connection;
                if ($cmd == 'BP05') { $connection->write("(" . $idInPacket . "AP05)"); } 
                elseif ($cmd == 'BR00') {
                    $this->line(self::CLR_REPLY . "📩 [TXT REPLY] From $device->name: " . substr($content, 16) . self::CLR_RST);
                }
            }
        }
    }

    private function getCRC16($hex) { $data = hex2bin($hex); $crc = 0xFFFF; for ($i = 0; $i < strlen($data); $i++) { $crc ^= ord($data[$i]); for ($j = 0; $j < 8; $j++) { if ($crc & 0x0001) $crc = ($crc >> 1) ^ 0x8408; else $crc >>= 1; } } return sprintf('%04x', ~$crc & 0xFFFF); }
}