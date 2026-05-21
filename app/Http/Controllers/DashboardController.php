<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        // Validasi input dasar
        $request->validate([
            'domain'  => 'required|url',
            'token'   => 'required|string',
            'phone'   => 'required|string',
            'message' => 'required|string',
        ]);

        $domain = rtrim($request->input('domain'), '/');
        $url = $domain . '/api/send-message';

        // Tembak API Wablas menggunakan Laravel HTTP Client
        $response = Http::withHeaders([
            'Authorization' => $request->input('token'),
            'Accept'        => 'application/json',
        ])->post($url, [
            'phone'   => $request->input('phone'),
            'message' => $request->input('message'),
        ]);

        // Kembalikan respons dari Wablas ke frontend GitHub Pages
        return response()->json($response->json(), $response->status());
    }

    public function indexVerifikasi()
    {
        // Ambil daftar kendaraan untuk pilihan dropdown
        // Sesuaikan query ini dengan tabel kendaraan yang Bapak miliki
        $vehicles = DB::table('vehicles')->select('id', 'name', 'plate_number')->get(); 
        
        return view('management.verifikasi', compact('vehicles'));
    }   

    public function getDataVerifikasi(Request $request)
    {
        $vehicleId = $request->vehicle_id;
        $date = $request->date; // Format: YYYY-MM-DD

        // 1. Ambil data titik parkir dari log GPS berdasarkan logikanya Bapak sebelumnya
        // Misal di sini kita asumsikan outputnya berupa kumpulan array/object titik parkir
        $parkingPoints = $this->hitungTitikParkirDariGps($vehicleId, $date); 

        // 2. Ambil data yang sudah pernah diverifikasi di database pada tanggal tersebut
        $verifiedData = DB::table('verifikasi_parkir')
            ->where('vehicle_id', $vehicleId)
            ->whereDate('waktu_mulai', $date)
            ->get()
            ->keyBy('waktu_mulai'); // Jadikan waktu_mulai sebagai key array agar mudah dicocokkan

        // 3. Gabungkan data GPS dengan data inputan Verifikasi
        $rekapVerifikasi = collect($parkingPoints)->map(function($point) use ($verifiedData) {
            $waktuMulai = $point->waktu_mulai; // Pastikan formatnya YYYY-MM-DD HH:ii:ss (WITA)
            
            // Cek apakah sudah pernah diinput oleh manajemen sebelumnya
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
            'vehicle_id' => 'required',
            'waktu_mulai' => 'required',
            'koordinat_gps' => 'required',
        ]);

        // Update jika sudah ada, Insert jika belum ada (Upsert)
        DB::table('verifikasi_parkir')->updateOrInsert(
            [
                'vehicle_id' => $request->vehicle_id,
                'waktu_mulai' => $request->waktu_mulai,
            ],
            [
                'koordinat_gps' => $request->koordinat_gps,
                'lat_long_pengerjaan' => $request->lat_long_pengerjaan,
                'keterangan' => $request->keterangan,
                'updated_at' => now() // Otomatis WITA sesuai setting server kita kemarin
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'Data verifikasi berhasil disimpan!']);
    }
}