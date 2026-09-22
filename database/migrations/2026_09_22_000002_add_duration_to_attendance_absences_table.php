<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_absences', function (Blueprint $table) {
            $table->enum('duration', ['whole_day', 'morning', 'afternoon'])->default('whole_day')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_absences', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};