<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Device;
use App\Models\Position;
use App\Models\VerifikasiParkir;
use App\Models\CommandLog;

class DashboardController extends Controller
{
    const TZ = 'Asia/Makassar';

    public function index()
    {
        return view('dashboard');
    }

    public function super_admin()
    {
        return view('command_center');
    }

    /**
     * API Telemetri Armada Real-Time.
     * Menggunakan kolom denormalisasi pada tabel devices untuk performa O(1) per armada
     * tanpa pemindaian jutaan baris log pada tabel positions.
     */
    public function getApiData()
    {
        $devices = Device::all();

        $result = $devices->map(function ($device) {
            $lastGpsTime = $device->last_gps_time 
                ? $device->last_gps_time->toDateTimeString() 
                : ($device->last_online ? $device->last_online->toDateTimeString() : null);

            return [
                'id'           => $device->id,
                'imei'         => $device->imei,
                'factory_id'   => $device->factory_id,
                'name'         => $device->name,
                'plate_number' => $device->plate_number,
                'module_type'  => $device->module_type ?? 'GT06N',
                'fuel_ratio'   => $device->fuel_ratio ?? 10.00,
                'acc_status'   => $device->acc_status ? 1 : 0,
                'fuel_status'  => $device->fuel_status ? 1 : 1,
                'last_online'  => $device->last_online ? $device->last_online->toDateTimeString() : null,
                'latitude'     => $device->last_latitude,
                'longitude'    => $device->last_longitude,
                'speed'        => round($device->last_speed ?? 0),
                'gps_time'     => $lastGpsTime,
            ];
        });

        return response()->json($result);
    }

    public function listDevices()
    {
        $devices = Device::orderBy('created_at', 'desc')->paginate(10);
        return view('devices.index', compact('devices'));
    }

    public function create()
    {
        return view('devices.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'imei'         => 'required|numeric|unique:devices,imei',
            'name'         => 'required|string|max:255',
            'plate_number' => 'required|string|max:50',
            'module_type'  => 'required|string|in:STANDARD,GT06N',
            'fuel_ratio'   => 'nullable|numeric|min:1|max:999',
        ]);

        Device::create([
            'imei'         => $request->imei,
            'name'         => $request->name,
            'plate_number' => strtoupper(trim($request->plate_number)),
            'module_type'  => $request->module_type,
            'fuel_ratio'   => $request->input('fuel_ratio', 10.00),
            'acc_status'   => 0,
            'fuel_status'  => 1,
        ]);

        return redirect()->route('devices.index')->with('success', 'Perangkat berhasil ditambahkan!');
    }

    public function destroy($id)
    {
        $device = Device::findOrFail($id);
        $device->delete();
        return redirect()->route('devices.index')->with('success', 'Perangkat berhasil dihapus.');
    }

    public function history($imei)
    {
        $device = Device::where('imei', $imei)->firstOrFail();
        return view('history', compact('device'));
    }

    public function getHistoryApi(Request $request, $imei)
    {
        $query = Position::where('imei', $imei);

        // 1. Jika mode "Tanggal Spesifik" (?date=...)
        if ($request->filled('date')) {
            $query->whereDate('gps_time', $request->date);
        } 
        // 2. Jika mode "Rentang Tanggal" (?start=...&end=...)
        elseif ($request->filled('start') && $request->filled('end')) {
            $query->where('gps_time', '>=', $request->start . ' 00:00:00')
                  ->where('gps_time', '<=', $request->end . ' 23:59:59');
        } 
        // 3. Default: Hari Ini (WITA)
        else {
            $query->whereDate('gps_time', Carbon::today(self::TZ));
        }

        $history = $query->orderBy('gps_time', 'asc')->get();
        return response()->json($history);
    }

    /**
     * Mengirim Perintah GPRS / Socket Relay ke Perangkat GPS.
     * Mendukung POST (best practice) dan GET (backward compatibility) dengan whitelist validasi perintah.
     */
    public function sendCommand(Request $request)
    {
        $imei = $request->input('imei', $request->query('imei'));
        $command = $request->input('command', $request->query('command'));

        if (!$imei || !$command) {
            return response()->json(['status' => 'error', 'msg' => 'Data tidak lengkap (IMEI dan Command wajib diisi).'], 422);
        }

        $command = trim($command);
        if (strlen($command) > 100) {
            return response()->json(['status' => 'error', 'msg' => 'Format perintah terlalu panjang.'], 422);
        }

        // Whitelist prefix perintah yang diperbolehkan untuk keamanan
        $allowedPrefixes = [
            'STATUS', 'VERSION', 'PARAM', 'RESET', 'RELAY', 
            'TIMER', 'SERVER', 'HBT', 'GPRS', 'WHERE', 'MODE', 'ACCON', 'ACCOFF'
        ];
        $isAllowed = false;
        $upperCmd = strtoupper($command);
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($upperCmd, $prefix)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            return response()->json([
                'status' => 'error', 
                'msg'    => 'Perintah tidak diizinkan oleh sistem.'
            ], 403);
        }

        // Buka koneksi TCP langsung ke bridge internal server GPS (Port 5023)
        $fp = @fsockopen("127.0.0.1", 5023, $errno, $errstr, 2);
        
        if (!$fp) {
            return response()->json([
                'status' => 'error', 
                'msg'    => 'Gagal terhubung ke Bridge GPS Server (Port 5023 offline).'
            ], 503);
        }

        // Kirim data dengan format IMEI|COMMAND
        fwrite($fp, "{$imei}|{$command}");
        
        // Baca respon dari bridge socket
        $response = fgets($fp, 1024);
        fclose($fp);

        $decoded = json_decode($response, true);
        return response()->json($decoded ?: [
            'status' => 'success', 
            'msg'    => 'Perintah telah diteruskan ke socket.'
        ]);
    }

    /**
     * Proxy WhatsApp Gateway dengan Proteksi SSRF.
     */
    public function sendProxy(Request $request)
    {
        $request->validate([
            'domain'  => 'required|url',
            'token'   => 'required|string',
            'phone'   => 'required|string',
            'action'  => 'nullable|string|in:check,send',
            'message' => 'required_if:action,send|string', 
        ]);

        $domain = rtrim($request->input('domain'), '/');
        $token  = $request->input('token');
        $phone  = $request->input('phone');
        $action = $request->input('action', 'send');

        // Proteksi SSRF: Pastikan domain tidak mengarah ke IP internal/loopback
        $host = parse_url($domain, PHP_URL_HOST);
        if (!$host) {
            return response()->json(['status' => false, 'message' => 'Domain tidak valid.'], 422);
        }

        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return response()->json(['status' => false, 'message' => 'Target domain tidak diizinkan.'], 403);
        }

        // 1. FASE CEK NOMOR AKTIF
        if ($action === 'check') {
            $url = 'https://phone.wablas.com/check-phone-number';

            try {
                $response = Http::timeout(10)->withHeaders([
                    'Authorization' => $token,
                    'url'           => $domain,
                    'Accept'        => 'application/json',
                ])->get($url, [
                    'phones' => $phone
                ]);

                $resData = $response->json();
                $isValid = false;

                if (isset($resData['data']) && is_array($resData['data'])) {
                    foreach ($resData['data'] as $item) {
                        if (isset($item['status']) && strtolower($item['status']) === 'online') {
                            $isValid = true;
                            break;
                        }
                    }
                } else {
                    $isValid = true;
                }

                return response()->json([
                    'status'     => $isValid,
                    'message'    => $isValid ? 'Online / Bypassed' : 'Offline',
                    'raw_wablas' => $resData
                ], 200);
            } catch (\Throwable $e) {
                return response()->json([
                    'status'     => true,
                    'message'    => 'Bypassed due to connection error',
                    'raw_wablas' => null
                ], 200);
            }
        } 
        // 2. FASE KIRIM PESAN UTAMA
        else {
            $url = $domain . '/api/send-message';

            try {
                $response = Http::timeout(15)->withHeaders([
                    'Authorization' => $token,
                    'Accept'        => 'application/json',
                ])->post($url, [
                    'phone'   => $phone,
                    'message' => $request->input('message'),
                ]);

                return response()->json($response->json(), $response->status());
            } catch (\Throwable $e) {
                return response()->json([
                    'status'  => false, 
                    'message' => 'Gagal mengirim pesan: ' . $e->getMessage()
                ], 500);
            }
        }
    }

    public function indexVerifikasi()
    {
        $devices = Device::select('id', 'name', 'plate_number')->orderBy('name')->get(); 
        return view('management.verifikasi', compact('devices'));
    }

    public function getDataVerifikasi(Request $request)
    {
        $deviceId = $request->device_id;
        $date = $request->date; // Format: YYYY-MM-DD

        $device = Device::find($deviceId);
        if (!$device) {
            return response()->json([]);
        }

        $positions = Position::where('imei', $device->imei)
            ->whereDate('gps_time', $date)
            ->orderBy('gps_time', 'asc')
            ->get();

        $parkingPoints = [];
        $lastP = null;

        foreach ($positions as $p) {
            if ($lastP) {
                $t1 = strtotime($p->gps_time);
                $t2 = strtotime($lastP->gps_time);
                $timeDiff = $t1 - $t2;

                // Mendeteksi jeda singgah/parkir (> 5 menit = 300 detik)
                if ($timeDiff > 300) {
                    $durasiMenit = floor($timeDiff / 60);
                    $waktuMulai = Carbon::parse($lastP->gps_time)->toDateTimeString();

                    $parkingPoints[] = (object)[
                        'waktu_mulai' => $waktuMulai,
                        'durasi'      => $durasiMenit . ' mnt',
                        'koordinat'   => $lastP->latitude . ',' . $lastP->longitude
                    ];
                }
            }
            $lastP = $p;
        }

        // Ambil data verifikasi manajemen yang sudah tersimpan
        $verifiedData = VerifikasiParkir::where('vehicle_id', $deviceId)
            ->whereDate('waktu_mulai', $date)
            ->get()
            ->keyBy(function($item) {
                return Carbon::parse($item->waktu_mulai)->toDateTimeString();
            });

        $rekapVerifikasi = collect($parkingPoints)->map(function($point) use ($verifiedData) {
            $waktuMulai = $point->waktu_mulai;
            $match = $verifiedData->get($waktuMulai);

            return [
                'waktu_mulai'         => $waktuMulai,
                'durasi'              => $point->durasi,
                'koordinat_gps'       => $point->koordinat,
                'lat_long_pengerjaan' => $match ? $match->lat_long_pengerjaan : '',
                'keterangan'          => $match ? $match->keterangan : '',
                'nama_driver'         => $match ? $match->nama_driver : '',
                'is_verified'         => $match ? true : false,
            ];
        });

        return response()->json($rekapVerifikasi);
    }

    public function simpanVerifikasi(Request $request)
    {
        $request->validate([
            'device_id'     => 'required|exists:devices,id',
            'waktu_mulai'   => 'required',
            'koordinat_gps' => 'required',
        ]);

        $waktuFormatted = Carbon::parse($request->waktu_mulai)->format('Y-m-d H:i:s');

        VerifikasiParkir::updateOrCreate(
            [
                'vehicle_id'  => $request->device_id,
                'waktu_mulai' => $waktuFormatted,
            ],
            [
                'koordinat_gps'       => $request->koordinat_gps,
                'lat_long_pengerjaan' => $request->lat_long_pengerjaan,
                'keterangan'          => $request->keterangan,
                'nama_driver'         => $request->nama_driver,
                'updated_at'          => Carbon::now(self::TZ),
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'Data verifikasi berhasil disimpan!']);
    }

    public function exportVerifikasi(Request $request)
    {
        $deviceId = $request->device_id;
        $date = $request->date;

        $device = Device::find($deviceId);
        if (!$device) {
            return redirect()->back()->with('error', 'Device tidak ditemukan.');
        }

        $positions = Position::where('imei', $device->imei)
            ->whereDate('gps_time', $date)
            ->orderBy('gps_time', 'asc')
            ->get();

        $parkingPoints = [];
        $lastP = null;

        foreach ($positions as $p) {
            if ($lastP) {
                $timeDiff = strtotime($p->gps_time) - strtotime($lastP->gps_time);
                if ($timeDiff > 300) {
                    $parkingPoints[] = [
                        'waktu_mulai' => Carbon::parse($lastP->gps_time)->toDateTimeString(),
                        'durasi'      => floor($timeDiff / 60) . ' mnt',
                        'koordinat'   => $lastP->latitude . ',' . $lastP->longitude,
                    ];
                }
            }
            $lastP = $p;
        }

        $verifiedData = VerifikasiParkir::where('vehicle_id', $deviceId)
            ->whereDate('waktu_mulai', $date)
            ->get()
            ->keyBy(function($item) {
                return Carbon::parse($item->waktu_mulai)->toDateTimeString();
            });

        $file = fopen('php://temp', 'r+');
        
        // Tambahkan BOM (Byte Order Mark) agar karakter dibaca rapi oleh Microsoft Excel
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($file, ["LAPORAN VERIFIKASI TITIK PARKIR MANAJEMEN"], ';');
        fputcsv($file, ["Kendaraan / Plat", $device->plate_number . " - " . $device->name], ';');
        fputcsv($file, ["Tanggal Rekap", $date], ';');
        fputcsv($file, [], ';');

        fputcsv($file, [
            "No", 
            "Mulai Parkir (WITA)", 
            "Durasi", 
            "Koordinat Asli GPS", 
            "Lat Long Pengerjaan (Verifikasi)", 
            "Keterangan Lapangan", 
            "Status Audit"
        ], ';');

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

        rewind($file);
        $csvContent = stream_get_contents($file);
        fclose($file);

        $filename = "Rekap_Verifikasi_Parkir_" . str_replace([' ', '/'], '_', $device->plate_number) . "_" . $date . ".csv";

        return response($csvContent, 200, [
            "Content-type"        => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }
}