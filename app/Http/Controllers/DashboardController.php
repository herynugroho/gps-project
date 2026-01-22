<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function getApiData()
    {
        // Ambil posisi terakhir setiap device
        $devices = DB::table('devices')
            ->select('devices.imei', 'devices.name', 'devices.plate_number', 
                     'positions.latitude', 'positions.longitude', 'positions.speed', 'positions.gps_time')
            ->join('positions', function ($join) {
                $join->on('devices.imei', '=', 'positions.imei')
                     ->whereRaw('positions.id IN (select MAX(id) from positions group by imei)');
            })
            ->get();

        return response()->json($devices);
    }
}