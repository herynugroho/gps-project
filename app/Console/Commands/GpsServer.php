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
    protected $description = 'Start TCP Server untuk menerima data GT02A/GT06';

    public function handle()
    {
        $port = $this->argument('port');
        $loop = \React\EventLoop\Factory::create();
        
        // Listen di semua IP (0.0.0.0) pada port yang ditentukan
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);

        $this->info("🚀 GPS Server Berjalan di Port $port...");
        $this->info("Menunggu koneksi dari GT02A...");

        $socket->on('connection', function (ConnectionInterface $connection) {
            $this->info("⚡ Device baru terhubung: " . $connection->getRemoteAddress());
            
            // Variabel sementara per koneksi
            $buffer = ''; 
            
            $connection->on('data', function ($data) use ($connection, &$buffer) {
                // GT02A mengirim data dalam Hex Binary
                $hex = bin2hex($data);
                $this->processPacket($connection, $hex);
            });

            $connection->on('close', function () {
                $this->info("❌ Device terputus.");
            });
            
            $connection->on('error', function (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
            });
        });

        $loop->run();
    }

    // --- LOGIKA PARSING PROTOKOL GT02A / GT06 ---
    private function processPacket($connection, $hexData)
    {
        // Validasi Header (Harus dimulai dengan 7878)
        if (substr($hexData, 0, 4) !== '7878') {
            return;
        }

        $length = hexdec(substr($hexData, 4, 2)); // Panjang Data
        $protocol = substr($hexData, 6, 2); // Tipe Pesan (Login/Lokasi/Heartbeat)

        // 1. HANDLER LOGIN (0x01)
        if ($protocol == '01') {
            $imei = substr($hexData, 8, 16);
            $serial = substr($hexData, -8, 4); // Serial Number untuk Reply
            
            $this->info("🔑 Login IMEI: $imei");
            
            // Simpan ke DB Devices
            $this->updateDeviceStatus($imei);

            // Respon Wajib (Agar alat tidak putus koneksi)
            // Format: Start(2) + Len(1) + Proto(1) + Serial(2) + CRC(2) + Stop(2)
            $response = hex2bin("78780501" . $serial . "D9DC0D0A"); 
            $connection->write($response);
        }

        // 2. HANDLER LOKASI (0x12 atau 0x22)
        elseif ($protocol == '12' || $protocol == '22') {
            // Parsing Data Lokasi
            // Data GT02A biasanya dimulai dari byte ke-4 (setelah proto)
            
            // Contoh Parsing Sederhana (Dokumentasi GT06 Protocol)
            // Date(6) + Sat(1) + Lat(4) + Lng(4) + Speed(1) + Status(2)
            
            try {
                // Decode Raw Data
                $rawData = substr($hexData, 8); 
                
                // Ambil Lat & Long (Hex to Decimal / 1800000 untuk GT06 standard)
                $latHex = substr($rawData, 14, 8);
                $lngHex = substr($rawData, 22, 8);
                $speedHex = substr($rawData, 30, 2);
                $courseHex = substr($rawData, 32, 4); // Status & Course gabung

                $lat = hexdec($latHex) / 1800000;
                $lng = hexdec($lngHex) / 1800000;
                $speed = hexdec($speedHex);

                // Perbaikan Koordinat (Indonesia ada di Lintang Selatan & Bujur Timur)
                // Logic ini harus disesuaikan jika alat mengirim bit flag untuk N/S/E/W
                // Untuk demo cepat, kita asumsikan default Makassar (South, East)
                // Biasanya perlu cek bit status course untuk +/- nya.
                
                // Cek bit course untuk status direction (Simplified)
                // Course binary = .... .... .... .... 
                
                // UPDATE: Untuk GT02A, seringkali Lat/Lng belum signed.
                // Jika lokasi melenceng ke China, berarti logic +/- nya terbalik.
                
                // Paksa ke koordinat Makassar/Indonesia jika hasil positif semua (debugging)
                if ($lng > 180) $lng = $lng - 360; // Normalisasi basic
                // GT06 Protocol: Lat & Lng are usually unsigned, status bit determines sign.
                // Simplified Hack for Indonesia (Southern Hemisphere):
                if ($lat > 0 && $lat < 90) { 
                    // Cek status bit di course byte. Jika Southern Hemisphere bit aktif.
                    // Untuk tutorial cepat, kita biarkan raw dulu. Nanti kalibrasi.
                }

                $this->info("📍 Lokasi: $lat, $lng | Speed: $speed km/h");

                // Simpan ke DB
                // Kita perlu IMEI. Di ReactPHP loop, kita harus simpan IMEI di memori koneksi
                // Tapi untuk simplifikasi, kita ambil device terakhir aktif di DB atau hardcode dummy dulu
                // Di Production: Gunakan Connection Attribute untuk simpan IMEI per sesi
                
                // CARA CEPAT DEMO: Ambil IMEI dari device yang barusan update 'last_online'
                $device = DB::table('devices')->orderBy('updated_at', 'desc')->first();
                if ($device) {
                    DB::table('positions')->insert([
                        'imei' => $device->imei,
                        'latitude' => $lat, // Perlu kalibrasi +/- nanti
                        'longitude' => $lng,
                        'speed' => $speed,
                        'course' => 0,
                        'gps_time' => Carbon::now(),
                        'created_at' => Carbon::now()
                    ]);
                }

            } catch (\Exception $e) {
                $this->error("Gagal parse lokasi: " . $e->getMessage());
            }
        }

        // 3. HANDLER HEARTBEAT (0x13)
        elseif ($protocol == '13') {
            $serial = substr($hexData, -8, 4);
            $this->info("❤️ Heartbeat received");
            // Wajib Reply
            $response = hex2bin("78780513" . $serial . "D9DC0D0A");
            $connection->write($response);
        }
    }

    private function updateDeviceStatus($imei)
    {
        DB::table('devices')->updateOrInsert(
            ['imei' => $imei],
            [
                'last_online' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'name' => 'GT02A Device', // Nama Default
                'plate_number' => 'TEST-01'
            ]
        );
    }
}