<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('license', function (Blueprint $table) {
            $table->timestamp('license_expiry')->nullable()->after('trial_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('license', function (Blueprint $table) {
            $table->dropColumn('license_expiry');
        });
    }
};
