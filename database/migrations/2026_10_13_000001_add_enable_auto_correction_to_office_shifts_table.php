<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->boolean('enable_auto_correction')->default(false)->after('grace_checkout_after_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->dropColumn('enable_auto_correction');
        });
    }
};
