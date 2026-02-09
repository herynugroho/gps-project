<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function getApiData()
    {
        // Mengambil data device beserta posisi terakhirnya
        $devices = DB::table('devices')
            ->select('devices.*', 'positions.latitude', 'positions.longitude', 'positions.speed', 'positions.course', 'positions.gps_time')
            ->leftJoin('positions', function ($join) {
                $join->on('devices.imei', '=', 'positions.imei')
                     ->whereRaw('positions.id IN (select MAX(id) from positions group by imei)');
            })
            ->get();

        return response()->json($devices);
    }

    public function listDevices()
    {
        $devices = DB::table('devices')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('devices.index', compact('devices'));
    }

    public function create()
    {
        return view('devices.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'imei' => 'required|numeric|unique:devices,imei',
            'name' => 'required|string|max:255',
            'plate_number' => 'required|string|max:20',
        ]);

        DB::table('devices')->insert([
            'imei' => $request->imei,
            'name' => $request->name,
            'plate_number' => $request->plate_number,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return redirect()->route('devices.index')->with('success', 'Kendaraan berhasil ditambahkan!');
    }

    public function destroy($id)
    {
        DB::table('devices')->where('id', $id)->delete();
        return redirect()->route('devices.index')->with('success', 'Kendaraan berhasil dihapus.');
    }

    public function history($imei)
    {
        $device = DB::table('devices')->where('imei', $imei)->first();
        if (!$device) abort(404);
        return view('history', compact('device'));
    }

    public function getHistoryApi($imei)
    {
        $history = DB::table('positions')
            ->where('imei', $imei)
            ->orderBy('gps_time', 'desc')
            ->limit(500)
            ->get()
            ->reverse()
            ->values();

        return response()->json($history);
    }
}