<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'imei',
        'factory_id',
        'plate_number',
        'module_type',
        'fuel_ratio',
        'name',
        'last_online',
        'acc_status',
        'fuel_status',
        'last_latitude',
        'last_longitude',
        'last_speed',
        'last_gps_time',
    ];

    protected $casts = [
        'acc_status' => 'boolean',
        'fuel_status' => 'boolean',
        'fuel_ratio' => 'float',
        'last_latitude' => 'float',
        'last_longitude' => 'float',
        'last_speed' => 'float',
        'last_online' => 'datetime',
        'last_gps_time' => 'datetime',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'imei', 'imei');
    }

    public function commandLogs(): HasMany
    {
        return $this->hasMany(CommandLog::class, 'imei', 'imei');
    }

    public function verifikasiParkir(): HasMany
    {
        return $this->hasMany(VerifikasiParkir::class, 'vehicle_id', 'id');
    }
}
