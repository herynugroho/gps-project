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
    protected $description = 'Hybrid GPS Server V7.3 - Robust Command Routing & Serial Tracking';

    private $connectionDeviceMap = []; 
    private $deviceTypeMap = [];       // IMEI => ['type' => 'BIN/TXT', 'header' => '7878/7979', 'serial' => 1]
    private $connectionBuffer = [];

    const CLR_RST   = "\e[0m";
    const CLR_GOWA  = "\e[1;36m"; 
    const CLR_SUCC  = "\e[1;32m"; 
    const CLR_REPLY = "\e[1;32;40m"; 
    const CLR_WARN  = "\e[1;31m"; 
    const CLR_SYS   = "\e[1;34m"; 

    public function handle()
    {
        $port = $this->argument('port');
        $controlPort = $this->argument('control_port');
        $loop = \React\EventLoop\Factory::create();

        // 1. GPS RECEIVER (5022)
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);
        $this->line(self::CLR_SYS . "📡 GPS RECEIVER ACTIVE ON PORT $port" . self::CLR_RST);

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
                        $this->line(self::CLR_WARN . "🔌 [OFFLINE] $imei" . self::CLR_RST);
                        unset($this->connectionDeviceMap[$imei]);
                        break;
                    }
                }
                unset($this->connectionBuffer[$connId]);
            });
        });

        // 2. TCP BRIDGE (5023)
        $controlSocket = new SocketServer("127.0.0.1:$controlPort", [], $loop);
        $this->line(self::CLR_SYS . "🌐 CONTROL BRIDGE ACTIVE ON PORT $controlPort" . self::CLR_RST);

        $controlSocket->on('connection', function (ConnectionInterface $connection) {
            $connection->on('data', function ($data) use ($connection) {
                $payload = trim($data);
                if (str_contains($payload, '|')) {
                    [$imei, $cmdText] = explode('|', $payload, 2);
                    if (isset($this->connectionDeviceMap[$imei])) {
                        $gpsConn = $this->connectionDeviceMap[$imei];
                        $devConfig = $this->deviceTypeMap[$imei] ?? ['type' => 'BIN', 'header' => '7878', 'serial' => 1];
                        
                        if ($devConfig['type'] === 'TXT') {
                            $device = DB::table('devices')->where('imei', $imei)->first();
                            $id = $device->factory_id ?? substr($imei, -12);
                            $packet = "(" . $id . $cmdText . ")";
                            $gpsConn->write($packet);
                            $this->line(self::CLR_SYS . "📡 [ROUTING CMD] Sent TEXT Format to $imei: $packet" . self::CLR_RST);
                        } else {
                            // Gunakan Serial yang meningkat & Header yang sesuai (2G/4G)
                            $currentSerial = $this->deviceTypeMap[$imei]['serial'] ?? 1;
                            $binary = $this->buildProtocol80($cmdText, $devConfig['header'], $currentSerial);
                            $gpsConn->write(hex2bin($binary));
                            $this->line(self::CLR_SYS . "📡 [ROUTING CMD] Sent BINARY Format to $imei: $binary" . self::CLR_RST);
                            
                            // Increment serial untuk pengiriman berikutnya
                            $this->deviceTypeMap[$imei]['serial'] = ($currentSerial + 1) % 65535;
                        }
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

        if ($protocolId == '01') {
            $terminalId = substr($hex, 8, 16);
            $device = DB::table('devices')->where('factory_id', 'LIKE', '%' . substr($terminalId, -8))->orWhere('imei', 'LIKE', '%' . substr($terminalId, -8))->first();
            if ($device) {
                $this->connectionDeviceMap[$device->imei] = $connection;
                $this->deviceTypeMap[$device->imei] = [
                    'type' => 'BIN', 
                    'header' => ($is4G ? '7979' : '7878'),
                    'serial' => 1
                ];
                $this->line(self::CLR_SUCC . "✅ [ONLINE] " . $device->name . " ($device->imei) Type: BINARY (" . ($is4G ? '4G':'2G') . ")" . self::CLR_RST);
                $connection->write(hex2bin("78780501" . $serialNum . $this->getCRC16("0501".$serialNum) . "0d0a"));
            }
        } 
        // --- PARSER BALASAN PERINTAH (PROT 21 / 15) ---
        elseif ($protocolId == '21' || $protocolId == '15') {
            $lenContentOffset = ($is4G ? 5 : 4) * 2;
            $contentStartOffset = ($is4G ? 10 : 9) * 2;
            
            $lenContent = hexdec(substr($hex, $lenContentOffset, 2));
            $contentHex = substr($hex, contentStartOffset, ($lenContent - 4) * 2); // Abaikan Flag 4 byte
            $replyText = hex2bin($contentHex);
            $this->line(self::CLR_REPLY . "📩 [REPLY] From " . ($currentImei ?? 'Unknown') . ": " . $replyText . self::CLR_RST);
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
                
                $device = DB::table('devices')->where('imei', $currentImei)->first();
                if($device) {
                    DB::table('positions')->insert(['imei'=>$device->imei,'latitude'=>$lat,'longitude'=>$lng,'speed'=>hexdec(substr($hex, ($latByte+8)*2, 2)),'gps_time'=>Carbon::now(),'created_at'=>Carbon::now()]);
                    DB::table('devices')->where('imei', $device->imei)->update(['acc_status'=>($hexdec(substr($hex, ($latByte+13)*2, 2)) & 0x02 ? 1:0),'last_online'=>Carbon::now()]);
                    $this->line(self::CLR_SUCC . "   📍 UPDATE: $device->name ($lat, $lng)" . self::CLR_RST);
                }
            }
        }
        elseif ($protocolId == '94') {
            $res = ($is4G ? "79790005" : "787805") . "94" . $serialNum;
            $connection->write(hex2bin($res . $this->getCRC16(substr($res, 4)) . "0d0a"));
        }
        elseif ($protocolId == '13') {
            $connection->write(hex2bin("78780513" . $serialNum . $this->getCRC16("0513".$serialNum) . "0d0a"));
        }
    }

    private function handleTextPacket($connection, $text, $connId) {
        if (preg_match('/\(([^)]+)\)/', $text, $match)) {
            $content = $match[1]; $idInPacket = substr($content, 0, 12);
            $cmd = substr($content, 12, 4);
            $device = DB::table('devices')->where('factory_id', 'LIKE', '%' . substr($idInPacket, -8))->first();
            if ($device) {
                $this->connectionDeviceMap[$device->imei] = $connection;
                $this->deviceTypeMap[$device->imei] = ['type' => 'TXT', 'header' => '7878', 'serial' => 1];
                
                if ($cmd == 'BP05') { 
                    $this->line(self::CLR_SUCC . "✅ [ONLINE] " . $device->name . " ($device->imei) Type: TEXT" . self::CLR_RST);
                    $connection->write("(" . $idInPacket . "AP05)"); 
                } 
                elseif ($cmd == 'BR00' || $cmd == 'BP04') {
                    if (preg_match('/[AV](\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', substr($content, 16), $m)) {
                        $lat = (floatval(substr($m[1], 0, 2)) + (floatval(substr($m[1], 2)) / 60)) * ($m[2] == 'S' ? -1 : 1);
                        $lng = (floatval(substr($m[3], 0, 3)) + (floatval(substr($m[3], 3)) / 60)) * ($m[4] == 'W' ? -1 : 1);
                        DB::table('positions')->insert(['imei'=>$device->imei,'latitude'=>$lat,'longitude'=>$lng,'speed'=>floatval($m[5])*1.852,'gps_time'=>Carbon::now(),'created_at'=>Carbon::now()]);
                        DB::table('devices')->where('imei', $device->imei)->update(['acc_status'=>1,'last_online'=>Carbon::now()]);
                        $this->line(self::CLR_SUCC . "   📍 UPDATE: $device->name ($lat, $lng)" . self::CLR_RST);
                    }
                } elseif (!str_contains($content, 'HSO')) {
                    // Hanya log jika isinya bukan Heartbeat data (HSO)
                    $this->line(self::CLR_REPLY . "📩 [TXT REPLY] From $device->name: " . substr($content, 12) . self::CLR_RST);
                }
            }
        }
    }

    private function buildProtocol80($command, $header = '7878', $serial = 1) {
        $cmdHex = bin2hex($command);
        $content = "80" . sprintf("%02x", strlen($command) + 4) . "00000000" . $cmdHex . sprintf("%04x", $serial);
        $len = sprintf(($header === '7979' ? "%04x" : "%02x"), strlen($content)/2);
        return $header . $len . $content . $this->getCRC16($len . $content) . "0d0a";
    }

    private function getCRC16($hex) { $data = hex2bin($hex); $crc = 0xFFFF; for ($i = 0; $i < strlen($data); $i++) { $crc ^= ord($data[$i]); for ($j = 0; $j < 8; $j++) { if ($crc & 0x0001) $crc = ($crc >> 1) ^ 0x8408; else $crc >>= 1; } } return sprintf('%04x', ~$crc & 0xFFFF); }
}