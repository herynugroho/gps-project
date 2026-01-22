<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * 1. Menampilkan Halaman Utama Dashboard (Peta Besar)
     */
    public function index()
    {
        return view('dashboard');
    }

    /**
     * 2. API JSON: Mengambil data posisi TERAKHIR semua mobil.
     * Digunakan oleh AJAX di dashboard utama.
     */
    public function getApiData()
    {
        $devices = DB::table('devices')
            ->select(
                'devices.imei', 
                'devices.name', 
                'devices.plate_number', 
                'positions.latitude', 
                'positions.longitude', 
                'positions.speed', 
                'positions.course', 
                'positions.gps_time'
            )
            ->leftjoin('positions', function ($join) {
                $join->on('devices.imei', '=', 'positions.imei')
                     ->whereRaw('positions.id IN (select MAX(id) from positions group by imei)');
            })
            ->get();

        return response()->json($devices);
    }

    /**
     * 3. Menampilkan Halaman Detail History (Satu Mobil)
     */
    public function history($imei)
    {
        $device = DB::table('devices')->where('imei', $imei)->first();
        
        if (!$device) {
            abort(404, 'Kendaraan tidak ditemukan');
        }

        return view('history', compact('device'));
    }

    /**
     * 4. API JSON: Mengambil jejak perjalanan (Polyline).
     */
    public function getHistoryApi($imei)
    {
        $positions = DB::table('positions')
            ->where('imei', $imei)
            ->orderBy('gps_time', 'desc') 
            ->limit(500) 
            ->get()
            ->reverse() 
            ->values();

        return response()->json($positions);
    }

    /**
     * 5. [BARU] Menampilkan Form Tambah Device
     */
    public function create()
    {
        return view('devices.create');
    }

    /**
     * 6. [BARU] Menyimpan Data Device Baru ke Database
     */
    public function store(Request $request)
    {
        // Validasi input form
        $request->validate([
            'imei' => 'required|numeric|unique:devices,imei',
            'name' => 'required|string|max:255',
            'plate_number' => 'required|string|max:20',
        ]);

        // Simpan ke tabel devices
        DB::table('devices')->insert([
            'imei' => $request->imei,
            'name' => $request->name,
            'plate_number' => $request->plate_number,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kembali ke dashboard
        return redirect('/')->with('success', 'Kendaraan berhasil ditambahkan!');
    }
}