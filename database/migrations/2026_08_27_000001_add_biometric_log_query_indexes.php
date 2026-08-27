<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode ?? '';

        try {
            // Some legacy device imports contain MySQL's zero datetime. Temporarily
            // relax this session only so MySQL can build an index over existing rows.
            DB::statement("SET SESSION sql_mode = ''");
            Schema::table('checkinout', function (Blueprint $table) {
                $table->index('CHECKTIME', 'checkinout_checktime_idx');
                $table->index('CHECKTYPE', 'checkinout_checktype_idx');
                $table->index('SENSORID', 'checkinout_sensorid_idx');
                $table->index('sn', 'checkinout_sn_idx');
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$originalSqlMode]);
        }
    }

    public function down(): void
    {
        Schema::table('checkinout', function (Blueprint $table) {
            $table->dropIndex('checkinout_checktime_idx');
            $table->dropIndex('checkinout_checktype_idx');
            $table->dropIndex('checkinout_sensorid_idx');
            $table->dropIndex('checkinout_sn_idx');
        });
    }
};
