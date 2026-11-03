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
        $query = Position::where('imei', $imei)
            ->whereBetween('latitude', [-90, 90])
            ->whereBetween('longitude', [-180, 180]);

        // 1. Jika mode "Tanggal Spesifik" (?date=...)
        if ($request->filled('date')) {
            $query->where('gps_time', '>=', $request->date . ' 00:00:00')
                  ->where('gps_time', '<=', $request->date . ' 23:59:59');
        } 
        // 2. Jika mode "Rentang Tanggal" (?start=...&end=...)
        elseif ($request->filled('start') && $request->filled('end')) {
            $query->where('gps_time', '>=', $request->start . ' 00:00:00')
                  ->where('gps_time', '<=', $request->end . ' 23:59:59');
        } 
        // 3. Default: Hari Ini (WITA)
        else {
            $today = Carbon::today(self::TZ)->toDateString();
            $query->where('gps_time', '>=', $today . ' 00:00:00')
                  ->where('gps_time', '<=', $today . ' 23:59:59');
        }

        $history = $query->orderBy('gps_time', 'asc')->get()->map(function ($item) {
            return [
                'id'        => $item->id,
                'imei'      => $item->imei,
                'latitude'  => (float) $item->latitude,
                'longitude' => (float) $item->longitude,
                'speed'     => (float) $item->speed,
                'course'    => (int) $item->course,
                'gps_time'  => $item->gps_time ? $item->gps_time->format('Y-m-d H:i:s') : null,
            ];
        });

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
            ->whereBetween('latitude', [-90, 90])
            ->whereBetween('longitude', [-180, 180])
            ->where('gps_time', '>=', $date . ' 00:00:00')
            ->where('gps_time', '<=', $date . ' 23:59:59')
            ->orderBy('gps_time', 'asc')
            ->get();

        $parkingPoints = $this->calculateParkingPoints($positions);

        // Ambil data verifikasi manajemen yang sudah tersimpan
        $verifiedData = VerifikasiParkir::where('vehicle_id', $deviceId)
            ->where('waktu_mulai', '>=', $date . ' 00:00:00')
            ->where('waktu_mulai', '<=', $date . ' 23:59:59')
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
            ->whereBetween('latitude', [-90, 90])
            ->whereBetween('longitude', [-180, 180])
            ->where('gps_time', '>=', $date . ' 00:00:00')
            ->where('gps_time', '<=', $date . ' 23:59:59')
            ->orderBy('gps_time', 'asc')
            ->get();

        $parkingPoints = $this->calculateParkingPoints($positions);

        $verifiedData = VerifikasiParkir::where('vehicle_id', $deviceId)
            ->where('waktu_mulai', '>=', $date . ' 00:00:00')
            ->where('waktu_mulai', '<=', $date . ' 23:59:59')
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
            $match = $verifiedData->get($point->waktu_mulai);
            fputcsv($file, [
                $index + 1,
                $point->waktu_mulai,
                $point->durasi,
                $point->koordinat,
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

    public function indexRekapRiwayat(Request $request)
    {
        $date = $request->query('date', Carbon::today(self::TZ)->toDateString());
        $recapData = $this->getFleetHistoryRecap($date);

        return view('management.rekap_riwayat', [
            'date'     => $date,
            'summary'  => $recapData['summary'],
            'vehicles' => $recapData['vehicles'],
        ]);
    }

    public function getRekapRiwayatData(Request $request)
    {
        $date = $request->query('date', Carbon::today(self::TZ)->toDateString());
        $recapData = $this->getFleetHistoryRecap($date);

        return response()->json($recapData);
    }

    public function exportRekapRiwayat(Request $request)
    {
        $date = $request->query('date', Carbon::today(self::TZ)->toDateString());
        $recapData = $this->getFleetHistoryRecap($date);

        $file = fopen('php://temp', 'r+');
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

        fputcsv($file, ["LAPORAN REKAPAN RIWAYAT & OPERASIONAL ARMADA - PRIMA TRACK"], ';');
        fputcsv($file, ["Tanggal Rekap", $date . ' (WITA)'], ';');
        fputcsv($file, ["Waktu Unduh", Carbon::now(self::TZ)->format('Y-m-d H:i:s') . ' WITA'], ';');
        fputcsv($file, [], ';');

        fputcsv($file, ["RINGKASAN TOTAL ARMADA"], ';');
        fputcsv($file, ["Total Armada Terdaftar", $recapData['summary']['total_vehicles'] . " Unit"], ';');
        fputcsv($file, ["Armada Beroperasi (Aktif)", $recapData['summary']['active_vehicles'] . " Unit"], ';');
        fputcsv($file, ["Total Titik Sinyal GPS", number_format($recapData['summary']['total_points'], 0, ',', '.') . " Titik"], ';');
        fputcsv($file, ["Total Jarak Tempuh", number_format($recapData['summary']['total_distance_km'], 2, ',', '.') . " km"], ';');
        fputcsv($file, ["Total Persinggahan / Parkir", $recapData['summary']['total_parking_count'] . " Titik (" . $recapData['summary']['total_parking_formatted'] . ")"], ';');
        fputcsv($file, ["Total Estimasi BBM", number_format($recapData['summary']['total_fuel_liters'], 2, ',', '.') . " Liter (Rp " . number_format($recapData['summary']['total_fuel_cost'], 0, ',', '.') . ")"], ';');
        fputcsv($file, [], ';');

        fputcsv($file, [
            "No",
            "Nama Armada",
            "Nomor Plat",
            "IMEI",
            "Tipe Modul",
            "Sinyal GPS (Titik)",
            "Rentang Jam Operasional (WITA)",
            "Titik Parkir",
            "Total Durasi Parkir",
            "Jarak Tempuh (km)",
            "Rasio BBM (km/L)",
            "Est. Konsumsi BBM (L)",
            "Est. Biaya BBM (Rp)",
            "Status Operasional"
        ], ';');

        foreach ($recapData['vehicles'] as $index => $v) {
            fputcsv($file, [
                $index + 1,
                $v['name'],
                $v['plate_number'],
                $v['imei'],
                $v['module_type'],
                $v['points'],
                $v['points'] > 0 ? ($v['first_gps_time'] . ' - ' . $v['last_gps_time']) : '-',
                $v['parking_count'] . ' Titik',
                $v['parking_formatted'],
                number_format($v['distance_km'], 2, ',', '.'),
                '1:' . $v['fuel_ratio'],
                number_format($v['fuel_liters'], 2, ',', '.'),
                number_format($v['fuel_cost'], 0, ',', '.'),
                $v['status_label']
            ], ';');
        }

        rewind($file);
        $csvContent = stream_get_contents($file);
        fclose($file);

        $filename = "Rekap_Riwayat_Armada_" . $date . ".csv";

        return response($csvContent, 200, [
            "Content-type"        => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }

    private function getFleetHistoryRecap(string $date): array
    {
        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';

        $devices = Device::orderBy('name', 'asc')->get();

        $allPositions = Position::where('gps_time', '>=', $start)
            ->where('gps_time', '<=', $end)
            ->whereBetween('latitude', [-90, 90])
            ->whereBetween('longitude', [-180, 180])
            ->orderBy('gps_time', 'asc')
            ->get()
            ->groupBy('imei');

        $vehicles = [];
        $totalDistanceKm = 0;
        $totalParkingCount = 0;
        $totalParkingSec = 0;
        $totalFuelLiters = 0;
        $totalFuelCost = 0;
        $totalPoints = 0;
        $activeVehiclesCount = 0;

        foreach ($devices as $dev) {
            $positions = $allPositions->get($dev->imei, collect());
            $pointsCount = $positions->count();
            $totalPoints += $pointsCount;

            $totalDistMeters = 0;
            $parkingEvents = [];
            $lastP = null;
            $firstTime = null;
            $lastTime = null;

            if ($pointsCount > 0) {
                $activeVehiclesCount++;
                $firstTime = $positions->first()->gps_time ? Carbon::parse($positions->first()->gps_time)->format('H:i') : null;
                $lastTime  = $positions->last()->gps_time ? Carbon::parse($positions->last()->gps_time)->format('H:i') : null;

                foreach ($positions as $p) {
                    $lat = (float) $p->latitude;
                    $lng = (float) $p->longitude;

                    if ($lastP) {
                        $t1 = strtotime($p->gps_time);
                        $t2 = strtotime($lastP->gps_time);
                        $timeDiff = $t1 - $t2;
                        $pointDist = $this->haversineMeters((float)$lastP->latitude, (float)$lastP->longitude, $lat, $lng);

                        if ($timeDiff > 300) {
                            $isMovingLoss = ($pointDist > 500 && (($lastP->speed ?? 0) > 15 || ($p->speed ?? 0) > 15));
                            if (!$isMovingLoss) {
                                $lastIdx = count($parkingEvents) - 1;
                                if ($lastIdx >= 0 && $this->haversineMeters($parkingEvents[$lastIdx]['lat'], $parkingEvents[$lastIdx]['lng'], (float)$lastP->latitude, (float)$lastP->longitude) < 100) {
                                    $parkingEvents[$lastIdx]['dur'] = $t1 - strtotime($parkingEvents[$lastIdx]['start']);
                                } else {
                                    $parkingEvents[] = [
                                        'lat'   => (float)$lastP->latitude,
                                        'lng'   => (float)$lastP->longitude,
                                        'start' => $lastP->gps_time,
                                        'dur'   => $timeDiff,
                                    ];
                                }
                            }
                        }

                        if ($pointDist < 50000) {
                            if ((($p->speed ?? 0) > 2) || $pointDist > 10) {
                                $totalDistMeters += $pointDist;
                            }
                        }
                    }
                    $lastP = $p;
                }
            }

            $distKm = round($totalDistMeters / 1000, 2);
            $fuelRatio = ($dev->fuel_ratio && $dev->fuel_ratio > 0) ? (float) $dev->fuel_ratio : 10.0;
            $fuelLiters = round($distKm / $fuelRatio, 2);
            $fuelCost = round($fuelLiters * 10000);

            $pCount = count($parkingEvents);
            $pSec = array_sum(array_column($parkingEvents, 'dur'));
            $pHours = floor($pSec / 3600);
            $pMins = floor(($pSec % 3600) / 60);
            $pFormatted = $pSec > 0 ? (($pHours > 0 ? "{$pHours}j " : '') . "{$pMins}m") : '0m';

            if ($pointsCount === 0) {
                $statusKey = 'offline';
                $statusLabel = 'Tidak Ada Sinyal';
            } elseif ($distKm > 0.5) {
                $statusKey = 'active';
                $statusLabel = 'Aktif Beroperasi';
            } else {
                $statusKey = 'parked';
                $statusLabel = 'Parkir / Diam';
            }

            $totalDistanceKm += $distKm;
            $totalParkingCount += $pCount;
            $totalParkingSec += $pSec;
            $totalFuelLiters += $fuelLiters;
            $totalFuelCost += $fuelCost;

            $vehicles[] = [
                'id'                => $dev->id,
                'name'              => $dev->name,
                'plate_number'      => $dev->plate_number,
                'imei'              => $dev->imei,
                'module_type'       => $dev->module_type ?? 'GT06N',
                'fuel_ratio'        => $fuelRatio,
                'points'            => $pointsCount,
                'first_gps_time'    => $firstTime,
                'last_gps_time'     => $lastTime,
                'distance_km'       => $distKm,
                'parking_count'     => $pCount,
                'parking_sec'       => $pSec,
                'parking_formatted' => $pFormatted,
                'fuel_liters'       => $fuelLiters,
                'fuel_cost'         => $fuelCost,
                'status_key'        => $statusKey,
                'status_label'      => $statusLabel,
            ];
        }

        $allHours = floor($totalParkingSec / 3600);
        $allMins = floor(($totalParkingSec % 3600) / 60);
        $allParkingFormatted = ($allHours > 0 ? "{$allHours}j " : '') . "{$allMins}m";

        return [
            'summary' => [
                'total_vehicles'          => count($devices),
                'active_vehicles'         => $activeVehiclesCount,
                'total_points'            => $totalPoints,
                'total_distance_km'       => round($totalDistanceKm, 2),
                'total_parking_count'     => $totalParkingCount,
                'total_parking_sec'       => $totalParkingSec,
                'total_parking_formatted' => $allParkingFormatted,
                'total_fuel_liters'       => round($totalFuelLiters, 2),
                'total_fuel_cost'         => $totalFuelCost,
            ],
            'vehicles' => $vehicles,
        ];
    }

    /**
     * Hitung titik parkir secara cerdas:
     * 1. Deteksi jeda henti (timeDiff > 300s).
     * 2. Filter blank spot (jika jarak > 500m dan kendaraan melaju > 15 km/h, bukan parkir).
     * 3. Smart Merge: Gabungkan henti berturut-turut di radius < 100m menjadi 1 sesi parkir utuh.
     */
    private function calculateParkingPoints($positions)
    {
        $parkingPoints = [];
        $currentParking = null;
        $lastP = null;

        foreach ($positions as $p) {
            if ($lastP) {
                $timeDiff = strtotime($p->gps_time) - strtotime($lastP->gps_time);

                if ($timeDiff > 300) {
                    $dist = $this->haversineMeters($lastP->latitude, $lastP->longitude, $p->latitude, $p->longitude);

                    // Abaikan lonjakan akibat hilang sinyal/blank spot saat kendaraan sedang melaju
                    $isMovingLoss = ($dist > 500 && (($lastP->speed ?? 0) > 15 || ($p->speed ?? 0) > 15));

                    if (!$isMovingLoss) {
                        if ($currentParking && $this->haversineMeters($currentParking['lat'], $currentParking['lng'], $lastP->latitude, $lastP->longitude) < 100) {
                            // Perpanjang sesi parkir yang sama di lokasi berdekatan
                            $currentParking['end_time'] = $p->gps_time;
                        } else {
                            if ($currentParking) {
                                $durasiMenit = max(1, floor((strtotime($currentParking['end_time']) - strtotime($currentParking['start_time'])) / 60));
                                $parkingPoints[] = (object)[
                                    'waktu_mulai' => Carbon::parse($currentParking['start_time'])->toDateTimeString(),
                                    'durasi'      => $durasiMenit . ' mnt',
                                    'koordinat'   => $currentParking['lat'] . ',' . $currentParking['lng'],
                                ];
                            }
                            $currentParking = [
                                'start_time' => $lastP->gps_time,
                                'end_time'   => $p->gps_time,
                                'lat'        => $lastP->latitude,
                                'lng'        => $lastP->longitude,
                            ];
                        }
                    }
                }
            }
            $lastP = $p;
        }

        if ($currentParking) {
            $durasiMenit = max(1, floor((strtotime($currentParking['end_time']) - strtotime($currentParking['start_time'])) / 60));
            $parkingPoints[] = (object)[
                'waktu_mulai' => Carbon::parse($currentParking['start_time'])->toDateTimeString(),
                'durasi'      => $durasiMenit . ' mnt',
                'koordinat'   => $currentParking['lat'] . ',' . $currentParking['lng'],
            ];
        }

        return $parkingPoints;
    }

    private function haversineMeters($lat1, $lon1, $lat2, $lon2)
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}