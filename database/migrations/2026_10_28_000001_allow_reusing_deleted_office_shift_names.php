<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // NULL permits repeated historical names while active names remain unique.
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->string('active_name')
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN name ELSE NULL END');
            $table->unique('active_name');
        });
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }

    public function down(): void
    {
        // Restore the old constraint first; fail without deleting history if names were reused.
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->unique('name');
        });
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->dropUnique(['active_name']);
            $table->dropColumn('active_name');
        });
    }
};
