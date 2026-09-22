<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_absences', function (Blueprint $table) {
            $table->enum('leave_type', ['cto', 'vacation', 'sick', 'spl', 'without_pay'])->nullable()->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_absences', function (Blueprint $table) {
            $table->dropColumn('leave_type');
        });
    }
};