<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToPositionsTable extends Migration
{
    public function up()
    {
        Schema::table('positions', function (Blueprint $table) {
            // Membuat composite index
            $table->index(['imei', 'id'], 'idx_imei_id');
        });
    }

    public function down()
    {
        Schema::table('positions', function (Blueprint $table) {
            // Menghapus index jika di-rollback
            $table->dropIndex('idx_imei_id');
        });
    }
}