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
                $this->processPacket($connection, $hex, $text);
            });

            $connection->on('close', function () {
                // Connection closed
            });
            
            $connection->on('error', function (\Exception $e) {
                // Error handling
            });
        });

        $loop->run();
    }

    private function processPacket($connection, $hexData, $textData)
    {
        // ==========================================================
        // PROTOKOL TEXT (365GPS/TOPIN)
        // Format: (SerialID + CMD + DATA)
        // ==========================================================
        if (str_starts_with($textData, '(') && str_ends_with($textData, ')')) {
            $content = substr($textData, 1, -1); // Hapus kurung
            
            $factoryId = substr($content, 0, 12);
            $cmd = substr($content, 12, 4);
            $data = substr($content, 16);

            $this->info("✅ MSG: $cmd | ID: $factoryId");

            // --- 1. LOGIN (BP05) ---
            if ($cmd == 'BP05') {
                $imei = substr($data, 0, 15);
                $this->info("🔑 Login IMEI: $imei");
                $this->updateDeviceStatus($imei);
                
                // Reply Login (AP05)
                $reply = "(" . $factoryId . "AP05)";
                $connection->write($reply);
            }

            // --- 2. LOKASI (BR00) ---
            // Contoh Data: 220101120000A2232.9806N11404.9355E000.1...
            elseif ($cmd == 'BR00') {
                // Kita ambil IMEI dari session/factoryID (Simplifikasi: Pakai ID Pabrik atau cari di DB)
                // Karena protokol ini tidak kirim IMEI di paket lokasi, kita harus cari IMEI based on FactoryID
                // ATAU: Asumsi alat sudah Login sebelumnya, kita cari device yang last_online-nya barusan update.
                
                // *Trik Cepat:* Kita parsing dulu, nanti simpan ke device yang cocok.
                // Regex untuk memecah format: YYMMDDHHMMSS + Status + Lat + N/S + Lon + E/W + Speed
                if (preg_match('/(\d{12})([AV])(\d+\.\d+)([NS])(\d+\.\d+)([EW])([\d\.]+)/', $data, $matches)) {
                    
                    $valid = $matches[2]; // A = Valid, V = Void (No GPS)
                    $rawLat = $matches[3];
                    $ns = $matches[4];
                    $rawLng = $matches[5];
                    $ew = $matches[6];
                    $speedKnots = $matches[7];

                    // Konversi Format NMEA (DDMM.MMMM) ke Decimal (DD.DDDD)
                    $lat = $this->dmToDecimal($rawLat);
                    $lng = $this->dmToDecimal($rawLng);

                    // Koreksi Minus (South / West)
                    if ($ns == 'S') $lat = $lat * -1;
                    if ($ew == 'W') $lng = $lng * -1;

                    // Speed Knots ke Km/h
                    $speed = floatval($speedKnots) * 1.852;

                    $this->info("📍 LOKASI: $lat, $lng | Speed: $speed km/h | Valid: $valid");

                    if ($valid == 'A') {
                        // Simpan ke DB. 
                        // Masalah: Paket BR00 tidak bawa IMEI. 
                        // Solusi: Kita pakai "Factory ID" ($factoryId) untuk mencari IMEI di log/db
                        // atau update device terakhir yang login dengan IP yang sama (Sangat advanced).
                        
                        // CARA SEMENTARA: Kita update SEMUA device yang punya Factory ID ini di namanya
                        // ATAU: Karena di dashboard Anda sudah input IMEI, kita anggap koneksi ini milik IMEI tersebut.
                        
                        // Cari device yang baru saja update (Login BP05)
                        $device = DB::table('devices')->orderBy('updated_at', 'desc')->first();
                        
                        if ($device) {
                            DB::table('positions')->insert([
                                'imei' => $device->imei,
                                'latitude' => $lat,
                                'longitude' => $lng,
                                'speed' => $speed,
                                'course' => 0,
                                'gps_time' => Carbon::now(),
                                'created_at' => Carbon::now()
                            ]);
                            $this->info("💾 Posisi disimpan untuk IMEI: " . $device->imei);
                        }
                    } else {
                        $this->warn("⚠️ GPS Void (Belum dapat satelit)");
                    }

                    // Reply Lokasi (AR00)
                    $connection->write("(" . $factoryId . "AR00)");
                }
            }

            // --- 3. HEARTBEAT (BZ00) ---
            elseif ($cmd == 'BZ00') {
                $connection->write("(" . $factoryId . "AZ00)");
                $this->info("❤️ Heartbeat");
            }

            // --- 4. HANDSHAKE (BP00) [BARU] ---
            elseif ($cmd == 'BP00') {
                $connection->write("(" . $factoryId . "AP00)");
                $this->info("🤝 Handshake BP00 Dibalas");
            }
        }
    }

    // Fungsi Konversi Koordinat GPS (DDMM.MMMM -> Decimal)
    private function dmToDecimal($dm) {
        // Lat: 2 digit pertama derajat. Lon: 3 digit pertama derajat.
        // Kita ambil titik sebagai patokan.
        $dotPos = strpos($dm, '.');
        if ($dotPos === false) return 0;

        // Menit adalah 2 digit sebelum titik + angka setelah titik
        $minutes = substr($dm, $dotPos - 2);
        // Derajat adalah sisanya di depan
        $degrees = substr($dm, 0, $dotPos - 2);

        return floatval($degrees) + (floatval($minutes) / 60);
    }

    private function updateDeviceStatus($imei)
    {
        try {
            DB::table('devices')->updateOrInsert(
                ['imei' => $imei],
                [
                    'last_online' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'name' => DB::raw('COALESCE(name, "Device '.$imei.'")') 
                ]
            );
        } catch (\Exception $e) {}
    }
}