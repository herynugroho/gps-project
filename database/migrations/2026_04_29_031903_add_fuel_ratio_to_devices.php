<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('devices', function (Blueprint $table) {
            // Menambahkan kolom fuel_ratio setelah kolom plate_number.
            // Angka 5,2 berarti total 5 digit, dengan 2 angka di belakang koma (contoh: 10.00)
            $table->decimal('fuel_ratio', 5, 2)->default(10.00)->after('plate_number');
        });
    }

    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('fuel_ratio');
        });
    }
};