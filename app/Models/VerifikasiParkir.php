<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifikasiParkir extends Model
{
    protected $table = 'verifikasi_parkir';

    protected $fillable = [
        'vehicle_id',
        'waktu_mulai',
        'koordinat_gps',
        'lat_long_pengerjaan',
        'keterangan',
        'nama_driver',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'vehicle_id', 'id');
    }
}
