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
    protected $description = 'Hybrid GPS Server V7.7 - 5 Minute Time Anchor & High Accuracy Reports';

    private $connectionDeviceMap = []; 
    private $deviceTypeMap = [];      
    private $connectionBuffer = [];

    // --- SETTING ZONA WAKTU MAKASSAR (WITA) ---
    const TZ = 'Asia/Makassar';

    const CLR_RST   = "\e[0m";
    const CLR_SUCC  = "\e[1;32m"; 
    const CLR_WARN  = "\e[1;31m"; 
    const CLR_SYS   = "\e[1;34m"; 

    public function handle()
    {
        $port = $this->argument('port');
        $controlPort = $this->argument('control_port');
        $loop = \React\EventLoop\Factory::create();

        $socket = new SocketServer("0.0.0.0:$port", [], $loop);
        $this->line(self::CLR_SYS . "📡 GPS RECEIVER ACTIVE ON PORT $port (TIMEZONE: WITA)" . self::CLR_RST);

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
                        unset($this->connectionDeviceMap[$imei]); break;
                    }
                }
                unset($this->connectionBuffer[$connId]);
            });
        });

        // Bridge untuk Command Center
        $controlSocket = new SocketServer("127.0.0.1:$controlPort", [], $loop);
        $controlSocket->on('connection', function ($connection) {
            $connection->on('data', function ($data) use ($connection) {
                $payload = trim($data);
                if (str_contains($payload, '|')) {
                    [$imei, $cmdText] = explode('|', $payload, 2);
                    if (isset($this->connectionDeviceMap[$imei])) {
                        $gpsConn = $this->connectionDeviceMap[$imei];
                        $devConfig = $this->deviceTypeMap[$imei] ?? ['type' => 'BIN', 'header' => '7878', 'serial' => 1];
                        
                        if ($devConfig['type'] === 'TXT') {
                            $device = DB::table('devices')->where('imei', $imei)->first();
                            $gpsConn->write("(" . ($device->factory_id ?? substr($imei, -12)) . $cmdText . ")");
                        } else {
                            $binary = $this->buildProtocol80($cmdText, $devConfig['header'], $devConfig['serial']);
                            $gpsConn->write(hex2bin($binary));
                            $this->deviceTypeMap[$imei]['serial'] = ($devConfig['serial'] + 1) % 65535;
                        }
                        // Log perintah menggunakan waktu WITA
                        DB::table('command_logs')->insert(['imei' => $imei, 'command' => $cmdText, 'created_at' => Carbon::now(self::TZ)]);
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
                $this->deviceTypeMap[$device->imei] = ['type' => 'BIN', 'header' => ($is4G ? '7979' : '7878'), 'serial' => 1];
                $this->line(self::CLR_SUCC . "✅ [ONLINE] " . $device->name . self::CLR_RST);
                $connection->write(hex2bin("78780501" . $serialNum . $this->getCRC16("0501".$serialNum) . "0d0a"));
            }
        } 
        elseif ($protocolId == '21' || $protocolId == '15') {
            $lenContent = hexdec(substr($hex, ($is4G ? 5 : 4) * 2, 2));
            $replyText = hex2bin(substr($hex, ($is4G ? 10 : 9) * 2, ($lenContent - 4) * 2));
            if ($currentImei) { $this->saveReplyToDb($currentImei, $replyText); }
        }
        elseif (in_array($protocolId, ['22', '12', '16', 'a0'])) {
            $latByte = ($is4G && $protocolId == '22') ? 12 : 11;
            if ($protocolId == '16') $latByte = $is4G ? 13 : 12;
            
            $timeHex = substr($hex, ($latByte - 7) * 2, 12);
            $gpsTime = $this->parseGpsTime($timeHex); // Hasil fungsi ini sudah WITA

            $latVal = hexdec(substr($hex, $latByte * 2, 8));
            $lngVal = hexdec(substr($hex, ($latByte + 4) * 2, 8));
            if ($latVal > 0) {
                $lat = $latVal / 1800000; $lng = $lngVal / 1800000;
                $courseStatus = hexdec(substr($hex, ($latByte + 9) * 2, 4));
                if (!($courseStatus & 0x0400)) $lat = -$lat;
                if ($courseStatus & 0x0800) $lng = -$lng;
                
                $device = DB::table('devices')->where('imei', $currentImei)->first();
                if($device) {
                    $this->savePosition($device, $lat, $lng, hexdec(substr($hex, ($latByte+8)*2, 2)), (hexdec(substr($hex, ($latByte+13)*2, 2)) & 0x02 ? 1:0), $gpsTime, "BIN");
                }
            }
        }
        elseif ($protocolId == '94' || $protocolId == '13') {
            $res = ($is4G && $protocolId == '94' ? "79790005" : "787805") . $protocolId . $serialNum;
            $connection->write(hex2bin($res . $this->getCRC16(substr($res, 4)) . "0d0a"));
        }
    }

    private function savePosition($device, $lat, $lng, $speed, $acc, $time, $source) {
        if ($lat == 0 || $lng == 0) return;

        $lastPos = DB::table('positions')->where('imei', $device->imei)->orderBy('id', 'desc')->first();
        $shouldSave = false;
        $reason = "";

        if (!$lastPos) {
            $shouldSave = true; $reason = "First data";
        } else {
            $distance = $this->calculateDistance($lastPos->latitude, $lastPos->longitude, $lat, $lng);
            $timeDiff = Carbon::now(self::TZ)->diffInSeconds(Carbon::parse($lastPos->created_at));

            if ($device->acc_status != $acc) { $shouldSave = true; $reason = "ACC Change"; }
            elseif ($speed > 5) { $shouldSave = true; $reason = "Moving"; }
            elseif ($timeDiff >= 300) { $shouldSave = true; $reason = "Time Anchor (5 Min)"; }
            elseif ($distance > 20) { $shouldSave = true; $reason = "Displacement"; }
        }

        if ($shouldSave) {
            DB::table('positions')->insert([
                'imei' => $device->imei,
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => $speed,
                'gps_time' => $time, // Ini Waktu WITA dari Satelit
                'created_at' => Carbon::now(self::TZ) // Ini Waktu WITA Server
            ]);
            $this->line(self::CLR_SUCC . "   📍 [$source] SAVE ($reason): $device->name" . self::CLR_RST);
        }
        $this->updateStatus($device, $acc);
    }

    private function parseGpsTime($hex) {
        // Konversi Hex Satelit (UTC) ke WITA (+8)
        $y = hexdec(substr($hex,0,2)); $m = hexdec(substr($hex,2,2)); $d = hexdec(substr($hex,4,2));
        $h = hexdec(substr($hex,6,2)); $i = hexdec(substr($hex,8,2)); $s = hexdec(substr($hex,10,2));
        return Carbon::create(2000+$y, $m, $d, $h, $i, $s, 'UTC')->setTimezone(self::TZ);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist); $dist = rad2deg($dist);
        return $dist * 60 * 1.1515 * 1609.344;
    }

    private function updateStatus($device, $acc) {
        DB::table('devices')->where('imei', $device->imei)->update(['acc_status' => $acc, 'last_online' => Carbon::now(self::TZ)]);
    }

    private function saveReplyToDb($imei, $reply) {
        $lastLog = DB::table('command_logs')->where('imei', $imei)->whereNull('reply')->orderBy('id', 'desc')->first();
        if ($lastLog) { DB::table('command_logs')->where('id', $lastLog->id)->update(['reply' => $reply, 'updated_at' => Carbon::now(self::TZ)]); }
    }

    private function getCRC16($hex) { $data = hex2bin($hex); $crc = 0xFFFF; for ($i = 0; $i < strlen($data); $i++) { $crc ^= ord($data[$i]); for ($j = 0; $j < 8; $j++) { if ($crc & 0x0001) $crc = ($crc >> 1) ^ 0x8408; else $crc >>= 1; } } return sprintf('%04x', ~$crc & 0xFFFF); }

    private function buildProtocol80($command, $header, $serial) {
        $content = "80" . sprintf("%02x", strlen($command) + 4) . "00000000" . bin2hex($command) . sprintf("%04x", $serial);
        $len = sprintf(($header === '7979' ? "%04x" : "%02x"), strlen($content)/2);
        return $header . $len . $content . $this->getCRC16($len . $content) . "0d0a";
    }

    private function handleTextPacket($connection, $text, $connId) { /* Logika teks tetap sama */ }
}