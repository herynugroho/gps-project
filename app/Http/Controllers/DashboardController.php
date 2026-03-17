<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

    public function getApiData() {
        // Mengambil data device beserta status ACC terbaru
        $devices = DB::table('devices')
            ->select('devices.*', 'positions.latitude', 'positions.longitude', 'positions.speed', 'positions.gps_time')
            ->leftJoin('positions', function ($join) {
                $join->on('devices.imei', '=', 'positions.imei')
                     ->whereRaw('positions.id IN (select MAX(id) from positions group by imei)');
            })->get();
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
        $range = $request->query('range', 'today');
        $query = DB::table('positions')->where('imei', $imei);

        if ($range === 'week') {
            $query->where('gps_time', '>=', Carbon::now()->startOfWeek());
        } else {
            $query->where('gps_time', '>=', Carbon::today());
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
}