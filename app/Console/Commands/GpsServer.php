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
    protected $description = 'Start TCP Server (Debug Mode)';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 DEBUG MODE: Server Berjalan di Port $port...");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $this->info("⚡ Device masuk: " . $connection->getRemoteAddress());
            
            $connection->on('data', function ($data) use ($connection) {
                // 1. Coba baca sebagai HEX (biasanya GT06)
                $hex = bin2hex($data);
                
                // 2. Coba baca sebagai TEXT (biasanya protokol H02/Sinotrack)
                $text = trim($data);

                // TAMPILKAN SEMUANYA KE LAYAR BIAR KETAHUAN
                $this->info("📦 RAW HEX  : " . $hex);
                $this->info("📄 RAW TEXT : " . $text);

                // Lanjut ke pemrosesan
                $this->processPacket($connection, $hex, $text);
            });

            $connection->on('close', function () {
                $this->info("❌ Device putus.");
            });
            
            $connection->on('error', function (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
            });
        });

        $loop->run();
    }

    private function processPacket($connection, $hexData, $textData)
    {
        // SKENARIO 1: PROTOKOL GT06 (Header 7878 atau 7979)
        if (substr($hexData, 0, 4) === '7878' || substr($hexData, 0, 4) === '7979') {
            $protocol = substr($hexData, 6, 2);
            
            // Login Packet
            if ($protocol == '01') {
                $imei = substr($hexData, 8, 16); // Ambil IMEI
                $serial = substr($hexData, -8, 4); 
                
                $this->info("✅ TERDETEKSI PROTOKOL GT06! IMEI: $imei");
                
                // Simpan Login
                $this->updateDeviceStatus($imei);

                // Reply Login (Wajib)
                $response = hex2bin("78780501" . $serial . "D9DC0D0A"); 
                $connection->write($response);
            }
            // Location Packet (0x12, 0x22, 0x16)
            elseif (in_array($protocol, ['12', '22', '16'])) {
                $this->info("📍 Data Lokasi GT06 masuk (Perlu parsing detail)");
                // (Logika parsing detail ada di kode sebelumnya, disederhanakan untuk debug ini)
            }
            // Heartbeat (0x13)
            elseif ($protocol == '13') {
                $serial = substr($hexData, -8, 4);
                $response = hex2bin("78780513" . $serial . "D9DC0D0A");
                $connection->write($response);
                $this->info("❤️ Heartbeat dibalas");
            }
        }

        // SKENARIO 2: PROTOKOL H02 (Teks diawali *HQ)
        // Contoh: *HQ,123456789012345,V1,....
        elseif (str_starts_with($textData, '*HQ')) {
            $parts = explode(',', $textData);
            if (count($parts) > 2) {
                $imei = $parts[1]; // IMEI biasanya ada di potongan kedua
                $this->info("✅ TERDETEKSI PROTOKOL H02! IMEI: $imei");
                $this->updateDeviceStatus($imei);
                // H02 biasanya tidak butuh reply khusus, atau balas IMEI saja
            }
        }
        
        else {
            $this->warn("⚠️ Protokol tidak dikenal. Cek Raw Hex di atas.");
        }
    }

    private function updateDeviceStatus($imei)
    {
        // Masukkan ke DB biar muncul di Dashboard
        // Gunakan try-catch biar gak error kalau DB belum siap
        try {
            DB::table('devices')->updateOrInsert(
                ['imei' => $imei],
                [
                    'last_online' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    // Kalau belum ada nama, kasih nama default
                    'name' => DB::raw('COALESCE(name, "New Device '.$imei.'")') 
                ]
            );
            $this->info("💾 Status device disimpan ke Database.");
        } catch (\Exception $e) {
            $this->error("Gagal simpan ke DB: " . $e->getMessage());
        }
    }
}