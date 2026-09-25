<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Lengkapi kolom di tabel devices
        Schema::table('devices', function (Blueprint $table) {
            if (!Schema::hasColumn('devices', 'factory_id')) {
                $table->string('factory_id', 50)->nullable()->after('imei')->index();
            }
            if (!Schema::hasColumn('devices', 'module_type')) {
                $table->string('module_type', 50)->default('GT06N')->after('plate_number');
            }
            if (!Schema::hasColumn('devices', 'acc_status')) {
                $table->boolean('acc_status')->default(0)->after('last_online');
            }
            if (!Schema::hasColumn('devices', 'fuel_status')) {
                $table->boolean('fuel_status')->default(1)->after('acc_status');
            }
            if (!Schema::hasColumn('devices', 'last_latitude')) {
                $table->double('last_latitude', 10, 7)->nullable()->after('fuel_status');
            }
            if (!Schema::hasColumn('devices', 'last_longitude')) {
                $table->double('last_longitude', 10, 7)->nullable()->after('last_latitude');
            }
            if (!Schema::hasColumn('devices', 'last_speed')) {
                $table->float('last_speed')->default(0)->after('last_longitude');
            }
            if (!Schema::hasColumn('devices', 'last_gps_time')) {
                $table->timestamp('last_gps_time')->nullable()->after('last_speed');
            }
        });

        // 2. Lengkapi kolom di tabel verifikasi_parkir
        Schema::table('verifikasi_parkir', function (Blueprint $table) {
            if (!Schema::hasColumn('verifikasi_parkir', 'nama_driver')) {
                $table->string('nama_driver')->nullable()->after('keterangan');
            }
        });

        // 3. Tambahkan kolom role pada tabel users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 50)->default('user')->after('email');
            }
        });

        // 4. Buat tabel command_logs yang digunakan oleh GpsServer
        if (!Schema::hasTable('command_logs')) {
            Schema::create('command_logs', function (Blueprint $table) {
                $table->id();
                $table->string('imei')->index();
                $table->string('command');
                $table->text('reply')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('command_logs')) {
            Schema::dropIfExists('command_logs');
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });

        Schema::table('verifikasi_parkir', function (Blueprint $table) {
            if (Schema::hasColumn('verifikasi_parkir', 'nama_driver')) {
                $table->dropColumn('nama_driver');
            }
        });

        Schema::table('devices', function (Blueprint $table) {
            $cols = [
                'factory_id', 'module_type', 'acc_status', 'fuel_status',
                'last_latitude', 'last_longitude', 'last_speed', 'last_gps_time'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('devices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
