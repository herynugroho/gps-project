<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index() {
        return view('dashboard');
    }

    public function super_admin() {
        return view('command_center');
    }

    // public function getApiData() {
    //     // Mengambil data device beserta status ACC terbaru
    //     $devices = DB::table('devices')
    //         ->select('devices.*', 'positions.latitude', 'positions.longitude', 'positions.speed', 'positions.gps_time')
    //         ->leftJoin('positions', function ($join) {
    //             $join->on('devices.imei', '=', 'positions.imei')
    //                  ->whereRaw('positions.id IN (select MAX(id) from positions group by imei)');
    //         })->get();
    //     return response()->json($devices);
    // }

    public function getApiData() {
        // 1. Kumpulkan dulu ID posisi terakhir dari masing-masing IMEI (Sangat Ringan)
        $latestPositions = DB::table('positions')
            ->select('imei', DB::raw('MAX(id) as max_id'))
            ->groupBy('imei');

        // 2. Lakukan Join Subquery ke tabel devices, lalu tarik koordinatnya
        $devices = DB::table('devices')
            ->select(
                'devices.*', 
                'positions.latitude', 
                'positions.longitude', 
                'positions.speed', 
                'positions.gps_time'
            )
            // Join ke subquery yang kita buat di atas
            ->leftJoinSub($latestPositions, 'latest', function ($join) {
                $join->on('devices.imei', '=', 'latest.imei');
            })
            // Terakhir, ambil detail lat/long berdasarkan ID terakhir (max_id)
            ->leftJoin('positions', 'latest.max_id', '=', 'positions.id')
            ->get();

        return response()->json($devices);
    }

    public function listDevices() {
        $devices = DB::table('devices')->orderBy('created_at', 'desc')->paginate(10);
        return view('devices.index', compact('devices'));
    }

    public function create() {
        return view('devices.create');
    }

    public function store(Request $request) {
        $request->validate([
            'imei' => 'required|numeric|unique:devices,imei',
            'name' => 'required',
            'plate_number' => 'required',
            'module_type' => 'required' // Parameter baru
        ]);

        DB::table('devices')->insert([
            'imei' => $request->imei,
            'name' => $request->name,
            'plate_number' => $request->plate_number,
            'module_type' => $request->module_type,
            'acc_status' => 0,
            'fuel_status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return redirect()->route('devices.index')->with('success', 'Perangkat berhasil ditambahkan!');
    }

    public function destroy($id) {
        DB::table('devices')->where('id', $id)->delete();
        return redirect()->route('devices.index')->with('success', 'Perangkat berhasil dihapus.');
    }

    public function history($imei) {
        $device = DB::table('devices')->where('imei', $imei)->first();
        if (!$device) abort(404);
        return view('history', compact('device'));
    }

    public function getHistoryApi(Request $request, $imei) {
        $query = DB::table('positions')->where('imei', $imei);

        // Pastikan menggunakan zona waktu Makassar agar "Hari Ini" tidak meleset
        $tz = 'Asia/Makassar'; 

        // 1. Jika mode "Tanggal Spesifik" (?date=...)
        if ($request->has('date')) {
            $query->whereDate('gps_time', $request->date);
        } 
        // 2. Jika mode "Rentang Tanggal" (?start=...&end=...)
        elseif ($request->has('start') && $request->has('end')) {
            $query->where('gps_time', '>=', $request->start . ' 00:00:00')
                ->where('gps_time', '<=', $request->end . ' 23:59:59');
        } 
        // 3. Default (Mode Hari Ini)
        else {
            $query->whereDate('gps_time', \Carbon\Carbon::today($tz));
        }

        $history = $query->orderBy('gps_time', 'asc')->get();
        
        return response()->json($history);
    }

     public function sendCommand(Request $request) {
        $imei = $request->query('imei');
        $command = $request->query('command');

        if (!$imei || !$command) {
            return response()->json(['status' => 'error', 'msg' => 'Data tidak lengkap']);
        }

        // Buka koneksi TCP langsung ke server GPS (Port 5023)
        $fp = @fsockopen("127.0.0.1", 5023, $errno, $errstr, 2);
        
        if (!$fp) {
            return response()->json([
                'status' => 'error', 
                'msg' => 'Gagal terhubung ke Bridge GPS: ' . $errstr
            ]);
        }

        // Kirim data dengan format IMEI|COMMAND
        fwrite($fp, "{$imei}|{$command}");
        
        // Baca respon (opsional)
        $response = fgets($fp, 1024);
        fclose($fp);

        return response()->json(json_decode($response) ?: [
            'status' => 'success', 
            'msg' => 'Perintah telah diteruskan ke socket.'
        ]);
    }

    public function sendProxy(Request $request)
    {
        // 1. Validasi input dinamis
        $request->validate([
            'domain'  => 'required|url',
            'token'   => 'required|string',
            'phone'   => 'required|string',
            'action'  => 'nullable|string|in:check,send',
            // Message hanya diwajibkan jika action-nya adalah mengirim pesan
            'message' => 'required_if:action,send|string', 
        ]);

        $domain = rtrim($request->input('domain'), '/');
        $token  = $request->input('token');
        $phone  = $request->input('phone');
        $action = $request->input('action', 'send'); // Default fallback ke 'send'

        // 2. LOGIKA CEK NOMOR AKTIF (Sesuai Dokumentasi GET Wablas)
        if ($action === 'check') {
            $url = 'https://bdg.wablas.com/check-phone-number';

            // Menggunakan withoutVerifying() sebagai padanan CURLOPT_SSL_VERIFYPEER => 0
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => $token,
                'url'           => $domain, // Wajib disisipkan di header sesuai aturan Wablas
                'Accept'        => 'application/json',
            ])->get($url, [
                'phones' => $phone
            ]);

            $resData = $response->json();
            $isValid = false;

            // Menerjemahkan format array Wablas ke boolean untuk Frontend
            if (isset($resData['data']) && is_array($resData['data'])) {
                foreach ($resData['data'] as $item) {
                    if (isset($item['status']) && strtolower($item['status']) === 'valid') {
                        $isValid = true;
                        break;
                    }
                }
            }

            return response()->json([
                'status'     => $isValid,
                'message'    => $isValid ? 'Valid' : 'Invalid',
                'raw_wablas' => $resData
            ], 200);
        } 

        // 3. LOGIKA KIRIM PESAN (Sesuai Dokumentasi POST Wablas)
        else {
            $url = $domain . '/api/send-message';

            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => $token,
                'Accept'        => 'application/json',
            ])->post($url, [
                'phone'   => $phone,
                'message' => $request->input('message'),
            ]);

            return response()->json($response->json(), $response->status());
        }
    }

    public function indexVerifikasi()
    {
        // PERBAIKAN: Mengambil data dari tabel 'devices' sesuai ERD Bapak
        $devices = DB::table('devices')->select('id', 'name', 'plate_number')->get(); 
        
        return view('management.verifikasi', compact('devices'));
    }

    public function getDataVerifikasi(Request $request)
    {
        $deviceId = $request->device_id;
        $date = $request->date; // Format: YYYY-MM-DD

        // 1. Ambil data imei device terlebih dahulu
        $device = DB::table('devices')->where('id', $deviceId)->first();
        if (!$device) {
            return response()->json([]);
        }

        // 2. Ambil semua log posisi gps berdasarkan imei dan tanggal terkait
        $positions = DB::table('positions')
            ->where('imei', $device->imei)
            ->whereDate('gps_time', $date)
            ->orderBy('gps_time', 'asc')
            ->get();

        $parkingPoints = [];
        $lastP = null;

        // 3. SALINAN LOGIKA FRONTEND BAPAK: Menghitung titik parkir (> 5 menit / 300 detik)
        foreach ($positions as $p) {
            if ($lastP) {
                $t1 = strtotime($p->gps_time);
                $t2 = strtotime($lastP->gps_time);
                $timeDiff = $t1 - $t2; // Selisih dalam satuan detik

                // 300 detik = 5 menit (Sama dengan 300000 ms di JS Bapak)
                if ($timeDiff > 300) {
                    $durasiMenit = floor($timeDiff / 60);
                    
                    $parkingPoints[] = (object)[
                        'waktu_mulai' => $lastP->gps_time,
                        'durasi' => $durasiMenit . ' mnt',
                        'koordinat' => $lastP->latitude . ',' . $lastP->longitude
                    ];
                }
            }
            $lastP = $p;
        }

        // 4. Ambil data yang sudah pernah diverifikasi di database
        // Kolom di ERD Bapak bernama 'vehicle_id', kita isi dengan ID dari tabel devices
        $verifiedData = DB::table('verifikasi_parkir')
            ->where('vehicle_id', $deviceId)
            ->whereDate('waktu_mulai', $date)
            ->get()
            ->keyBy('waktu_mulai');

        // 5. Satukan data koordinat parkir dengan data inputan verifikasi manajemen
        $rekapVerifikasi = collect($parkingPoints)->map(function($point) use ($verifiedData) {
            $waktuMulai = $point->waktu_mulai;
            $match = $verifiedData->get($waktuMulai);

            return [
                'waktu_mulai' => $waktuMulai,
                'durasi' => $point->durasi,
                'koordinat_gps' => $point->koordinat,
                'lat_long_pengerjaan' => $match ? $match->lat_long_pengerjaan : '',
                'keterangan' => $match ? $match->keterangan : '',
                'is_verified' => $match ? true : false
            ];
        });

        return response()->json($rekapVerifikasi);
    }

    public function simpanVerifikasi(Request $request)
    {
        $request->validate([
            'device_id' => 'required',
            'waktu_mulai' => 'required',
            'koordinat_gps' => 'required',
        ]);

        // Simpan ke tabel verifikasi_parkir (vehicle_id diisi id device)
        DB::table('verifikasi_parkir')->updateOrInsert(
            [
                'vehicle_id' => $request->device_id,
                'waktu_mulai' => $request->waktu_mulai,
            ],
            [
                'koordinat_gps' => $request->koordinat_gps,
                'lat_long_pengerjaan' => $request->lat_long_pengerjaan,
                'keterangan' => $request->keterangan,
                'updated_at' => now() // Mengikuti WITA server
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'Data verifikasi berhasil disimpan!']);
    }

    public function exportVerifikasi(Request $request)
    {
        $deviceId = $request->device_id;
        $date = $request->date;

        // 1. Ambil data device berdasarkan ID
        $device = DB::table('devices')->where('id', $deviceId)->first();
        if (!$device) {
            return redirect()->back()->with('error', 'Device tidak ditemukan.');
        }

        // 2. Ambil log posisi GPS
        $positions = DB::table('positions')
            ->where('imei', $device->imei)
            ->whereDate('gps_time', $date)
            ->orderBy('gps_time', 'asc')
            ->get();

        $parkingPoints = [];
        $lastP = null;

        // 3. Hitung interval titik parkir (> 5 menit)
        foreach ($positions as $p) {
            if ($lastP) {
                $timeDiff = strtotime($p->gps_time) - strtotime($lastP->gps_time);
                if ($timeDiff > 300) {
                    $parkingPoints[] = [
                        'waktu_mulai' => $lastP->gps_time,
                        'durasi' => floor($timeDiff / 60) . ' mnt',
                        'koordinat' => $lastP->latitude . ',' . $lastP->longitude
                    ];
                }
            }
            $lastP = $p;
        }

        // 4. Ambil data hasil verifikasi manajemen jika ada
        $verifiedData = DB::table('verifikasi_parkir')
            ->where('vehicle_id', $deviceId)
            ->whereDate('waktu_mulai', $date)
            ->get()
            ->keyBy('waktu_mulai');

        // =========================================================
        // METODE AMAN: Tulis ke Memory Buffer Menggunakan Titik Koma (;)
        // =========================================================
        $file = fopen('php://temp', 'r+');
        
        // Tambahkan BOM (Byte Order Mark) agar karakter dibaca rapi oleh Microsoft Excel
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

        // Judul & Metadata Laporan di Excel (Tambahkan parameter ';' di bagian akhir fputcsv)
        fputcsv($file, ["LAPORAN VERIFIKASI TITIK PARKIR MANAJEMEN"], ';');
        fputcsv($file, ["Kendaraan / Plat", $device->plate_number . " - " . $device->name], ';');
        fputcsv($file, ["Tanggal Rekap", $date], ';');
        fputcsv($file, [], ';'); // Jeda baris kosong

        // Judul Kolom Tabel
        fputcsv($file, [
            "No", 
            "Mulai Parkir (WITA)", 
            "Durasi", 
            "Koordinat Asli GPS", 
            "Lat Long Pengerjaan (Verifikasi)", 
            "Keterangan Lapangan", 
            "Status Audit"
        ], ';');

        // Mengisi Baris Data
        foreach ($parkingPoints as $index => $point) {
            $match = $verifiedData->get($point['waktu_mulai']);
            fputcsv($file, [
                $index + 1,
                $point['waktu_mulai'],
                $point['durasi'],
                $point['koordinat'],
                $match ? $match->lat_long_pengerjaan : '',
                $match ? $match->keterangan : '',
                $match ? 'Sudah Diverifikasi' : 'Belum Diverifikasi'
            ], ';');
        }

        // Baca isi file yang sudah dikumpulkan di memory
        rewind($file);
        $csvContent = stream_get_contents($file);
        fclose($file);

        // Format penamaan file download
        $filename = "Rekap_Verifikasi_Parkir_" . str_replace([' ', '/'], '_', $device->plate_number) . "_" . $date . ".csv";

        // Kirim response utuh ke browser
        return response($csvContent, 200, [
            "Content-type"        => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }
}