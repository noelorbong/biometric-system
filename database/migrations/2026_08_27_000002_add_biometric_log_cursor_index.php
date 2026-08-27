<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode ?? '';

        try {
            DB::statement("SET SESSION sql_mode = ''");
            Schema::table('checkinout', function (Blueprint $table) {
                $table->index(['CHECKTIME', 'id'], 'checkinout_checktime_id_idx');
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$originalSqlMode]);
        }
    }

    public function down(): void
    {
        Schema::table('checkinout', function (Blueprint $table) {
            $table->dropIndex('checkinout_checktime_id_idx');
        });
    }
};
