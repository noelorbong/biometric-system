<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->boolean('grace_enabled')->default(false)->after('is_flexible');
            $table->unsignedSmallInteger('grace_before_minutes')->default(0)->after('grace_enabled');
            $table->unsignedSmallInteger('grace_after_minutes')->default(0)->after('grace_before_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'grace_enabled',
                'grace_before_minutes',
                'grace_after_minutes',
            ]);
        });
    }
};
