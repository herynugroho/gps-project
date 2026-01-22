<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Tabel untuk menyimpan daftar perangkat (IMEI)
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('imei')->unique();
            $table->string('plate_number')->nullable(); // Plat Nomor
            $table->string('name')->nullable(); // Nama Mobil (misal: Avanza Putih)
            $table->timestamp('last_online')->nullable();
            $table->timestamps();
        });

        // 2. Tabel untuk menyimpan history perjalanan (Koordinat)
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('imei')->index();
            $table->double('latitude', 10, 7);
            $table->double('longitude', 10, 7);
            $table->float('speed')->default(0); // km/h
            $table->integer('course')->default(0); // Arah (0-360 derajat)
            $table->timestamp('gps_time'); // Waktu kejadian
            $table->timestamps(); // Waktu server terima data

            // Indexing biar query peta cepat
            $table->index(['imei', 'gps_time']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('devices');
    }
};