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
    protected $description = 'Final Hybrid GPS Server V5.2';

    private $connectionDeviceMap = [];
    private $connectionBuffer = [];

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 PRIMA GPS HYBRID SERVER V5.2 STARTED ON PORT $port");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connId = spl_object_hash($connection);
            $this->connectionBuffer[$connId] = '';
            
            $connection->on('data', function ($data) use ($connection, $connId) {
                $this->connectionBuffer[$connId] .= bin2hex($data);
                $this->processBuffer($connection, $connId);
            });

            $connection->on('close', function() use ($connId) {
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
                
                $this->handleBinaryPacket($connection, substr($buffer, 0, $totalLenHex), $connId, $is4G);
                $buffer = substr($buffer, $totalLenHex);
            } 
            elseif (str_starts_with($buffer, '28')) { // ASCII '('
                $endPos = strpos($buffer, '29'); // ASCII ')'
                if ($endPos === false) break;
                $this->handleTextPacket($connection, hex2bin(substr($buffer, 0, $endPos + 2)), $connId);
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
                $this->info("✅ [4G] LOGIN: " . $device->name);
                $connection->write(hex2bin("78780501" . $serialNum . $this->getCRC16("0501".$serialNum) . "0d0a"));
                $this->updateStatus($device, 0);
            }
        } else {
            $device = $this->connectionDeviceMap[$connId] ?? null;
            if (!$device) return;

            if ($protocolId == '94') { // INFO
                $connection->write(hex2bin(($is4G ? "7979" : "7878") . "000594" . $serialNum . $this->getCRC16("000594".$serialNum) . "0d0a"));
                $this->updateStatus($device, $device->acc_status);
            } 
            elseif ($protocolId == '13') { // HEARTBEAT
                $connection->write(hex2bin("78780513" . $serialNum . $this->getCRC16("0513".$serialNum) . "0d0a"));
                $this->updateStatus($device, $device->acc_status);
            }
            elseif ($protocolId == '22' || $protocolId == '12') { // LOCATION
                $latByte = $is4G ? 12 : 11; $lngByte = $is4G ? 16 : 15; $spdByte = $is4G ? 20 : 19;
                $lat = hexdec(substr($hex, $latByte * 2, 8)) / 1800000;
                $lng = hexdec(substr($hex, $lngByte * 2, 8)) / 1800000;
                $speed = hexdec(substr($hex, $spdByte * 2, 2));

                // Deteksi ACC dari Status Byte GT06N
                $statusByte = hexdec(substr($hex, ($latByte + 13) * 2, 2));
                $acc = ($statusByte & 0x02) ? 1 : 0;

                $this->savePosition($device, $lat, $lng, $speed, $acc);
                $this->info("📍 [4G] UPDATE: " . $device->name . " ($lat,$lng)");
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
                if ($cmd == 'BP05') {
                    $connection->write("(" . $idInPacket . "AP05)");
                    $this->info("✅ [TXT] LOGIN: " . $device->name);
                } 
                elseif (($cmd == 'BR00' || $cmd == 'BP04')) {
                    if (preg_match('/[AV](\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $m)) {
                        $lat = $this->dmToDecimal($m[1]) * ($m[2] == 'S' ? -1 : 1);
                        $lng = $this->dmToDecimal($m[3]) * ($m[4] == 'W' ? -1 : 1);
                        $speed = floatval($m[5]) * 1.852;
                        $this->savePosition($device, $lat, $lng, $speed, ($speed > 5 ? 1 : 0));
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
        return DB::table('devices')->where('factory_id', 'LIKE', '%' . $shortId)->orWhere('imei', 'LIKE', '%' . $shortId)->first();
    }

    private function savePosition($device, $lat, $lng, $speed, $acc) {
        if ($lat == 0 || $lng == 0) return;
        DB::table('positions')->insert(['imei'=>$device->imei,'latitude'=>$lat,'longitude'=>$lng,'speed'=>$speed,'gps_time'=>Carbon::now(),'created_at'=>Carbon::now()]);
        $this->updateStatus($device, $acc);
    }

    private function updateStatus($device, $acc) {
        DB::table('devices')->where('imei', $device->imei)->update(['acc_status'=>$acc,'last_online'=>Carbon::now(),'updated_at'=>Carbon::now()]);
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