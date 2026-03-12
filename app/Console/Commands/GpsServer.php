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
    protected $description = 'GPS Server with Raw Hex Debug for GT06N Troubleshooting';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🔍 DEBUG MODE: PRIMA GPS SERVER STARTED ON PORT $port");
        $this->info("-------------------------------------------------------");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $connection->on('data', function ($data) use ($connection) {
                $hex = bin2hex($data);
                $this->info("📥 RAW HEX RECEIVED: " . $hex); // INI AKAN MUNCUL DI TERMINAL
                
                $this->handleIncomingData($connection, $data);
            });
        });

        $loop->run();
    }

    private function handleIncomingData($connection, $raw)
    {
        $hex = bin2hex($raw);
        $text = trim($raw);

        // 1. PROTOKOL TEKS (MODUL LAMA)
        if (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $this->parseTextProtocol($connection, $text);
        } 
        // 2. PROTOKOL BINER CONCOX (GT06N 4G)
        elseif (str_starts_with($hex, '7878')) {
            $this->parseBinaryProtocol($connection, $hex);
        }
    }

    private function parseBinaryProtocol($connection, $hex)
    {
        // Concox Protocol: 78 78 [Length] [Protocol ID] [Data] [Serial] [CRC] 0D 0A
        $protocolId = substr($hex, 6, 2);
        
        // Ambil Serial Number (2 byte sebelum CRC & Stop bit)
        $serialNum = substr($hex, strlen($hex) - 12, 4);

        if ($protocolId == '01') { // Login Packet
            $terminalId = substr($hex, 8, 16);
            $this->info("🔑 GT06N LOGIN ATTEMPT: " . $terminalId);

            // Jawab Login: 78 78 05 01 [Serial] [CRC] 0D 0A
            $resBody = "0501" . $serialNum;
            $crc = $this->getCRC16($resBody);
            $responseHex = "7878" . $resBody . $crc . "0d0a";
            
            $this->info("📤 SENDING LOGIN RESPONSE: " . $responseHex);
            $connection->write(hex2bin($responseHex));
        } 
        elseif ($protocolId == '22' || $protocolId == '12') { // GPS Location
            $this->info("📍 RECEIVED GPS PACKET FROM GT06N");
            // ... logika parsing lokasi ...
        }
    }

    // Fungsi Kalkulasi CRC16 X25/ITU-T (Wajib untuk Concox)
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

    // ... sisa fungsi parseTextProtocol dan lainnya tetap sama ...
    private function parseTextProtocol($connection, $text) { /* ... */ }
}