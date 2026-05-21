<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verifikasi_parkir', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_id'); // Menyesuaikan dengan ID kendaraan Bapak
            $table->dateTime('waktu_mulai'); // Sebagai key pencocokan waktu parkir
            $table->string('koordinat_gps'); // Koordinat asli dari GPS
            $table->string('lat_long_pengerjaan')->nullable(); // Kolom baru inputan manajemen
            $table->text('keterangan')->nullable(); // Kolom baru inputan manajemen
            $table->timestamps();
            
            // Indeks agar pencarian data cepat
            $table->index(['vehicle_id', 'waktu_mulai']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('verifikasi_parkir');
    }
};
