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
    protected $description = 'Start TCP Server (Universal Protocol)';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 SERVER READY: Port $port");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $this->info("⚡ Device masuk: " . $connection->getRemoteAddress());
            
            $connection->on('data', function ($data) use ($connection) {
                $hex = bin2hex($data);
                $text = trim($data);

                // DEBUG LOG
                // $this->info("📦 RAW: " . $text); 

                $this->processPacket($connection, $hex, $text);
            });

            $connection->on('close', function () {
                // $this->info("❌ Device putus.");
            });
            
            $connection->on('error', function (\Exception $e) {
                // $this->error("Error: " . $e->getMessage());
            });
        });

        $loop->run();
    }

    private function processPacket($connection, $hexData, $textData)
    {
        // ==========================================================
        // SKENARIO 1: PROTOKOL TEXT (YANG ALAT ANDA PAKAI)
        // Format: (SerialID + CMD + DATA)
        // Contoh: (028044735775BP05355228044735775...)
        // ==========================================================
        if (str_starts_with($textData, '(') && str_ends_with($textData, ')')) {
            $content = substr($textData, 1, -1); // Hapus kurung ( )
            
            // Parsing Header
            // 12 digit pertama biasanya ID Pabrik
            $factoryId = substr($content, 0, 12);
            // 4 digit berikutnya adalah Command (BP05, BZ00, BR00)
            $cmd = substr($content, 12, 4);
            // Sisanya adalah Data
            $data = substr($content, 16);

            $this->info("✅ TERDETEKSI PROTOKOL TEXT! ID: $factoryId CMD: $cmd");

            // --- HANDLER 1: LOGIN (BP05) ---
            if ($cmd == 'BP05') {
                // 15 digit pertama di data adalah IMEI
                $imei = substr($data, 0, 15);
                $this->info("🔑 Login IMEI: $imei");
                
                $this->updateDeviceStatus($imei);
                
                // Wajib Reply: Ganti 'B' jadi 'A' -> (ID + AP05)
                $reply = "(" . $factoryId . "AP05)";
                $connection->write($reply);
                $this->info("📤 Reply Login Terkirim");
            }

            // --- HANDLER 2: LOKASI GPS (BR00) ---
            // Format: YYMMDDHHMMSS A LAT N LON E SPEED ...
            elseif ($cmd == 'BR00') {
                $this->info("📍 Data Lokasi Masuk!");
                
                // Parsing sederhana format BR00
                // Data: 220520102030A2233.4444N11333.4444E000.1...
                // (Implementasi regex parsing sederhana)
                // Kita cari pola A/V (Valid/Void)
                
                // Reply dulu biar alat senang
                $reply = "(" . $factoryId . "AR00)";
                $connection->write($reply);

                // TODO: Parsing koordinat BR00 nanti saat data masuk
                // Untuk sekarang kita pastikan Login berhasil dulu
            }

            // --- HANDLER 3: HEARTBEAT/LBS (BZ00) ---
            elseif ($cmd == 'BZ00') {
                $reply = "(" . $factoryId . "AZ00)";
                $connection->write($reply);
                $this->info("❤️ Heartbeat Dibalas");
            }
        }

        // ==========================================================
        // SKENARIO 2: PROTOKOL GT06 (Untuk Jaga-jaga)
        // ==========================================================
        elseif (substr($hexData, 0, 4) === '7878') {
            // (Kode GT06 yang lama tetap kita simpan biar universal)
            $protocol = substr($hexData, 6, 2);
            if ($protocol == '01') {
                $imei = substr($hexData, 8, 16);
                $serial = substr($hexData, -8, 4);
                $this->info("Login GT06: $imei");
                $this->updateDeviceStatus($imei);
                $connection->write(hex2bin("78780501" . $serial . "D9DC0D0A"));
            }
        }
    }

    private function updateDeviceStatus($imei)
    {
        try {
            DB::table('devices')->updateOrInsert(
                ['imei' => $imei],
                [
                    'last_online' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'name' => DB::raw('COALESCE(name, "New Device '.$imei.'")') 
                ]
            );
            $this->info("💾 Device Disimpan: $imei");
        } catch (\Exception $e) {
            $this->error("DB Error: " . $e->getMessage());
        }
    }
}