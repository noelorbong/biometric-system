<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('grace_checkin_before_minutes')->default(0)->after('grace_after_minutes');
            $table->unsignedSmallInteger('grace_checkin_after_minutes')->default(0)->after('grace_checkin_before_minutes');
            $table->unsignedSmallInteger('grace_checkout_before_minutes')->default(0)->after('grace_checkin_after_minutes');
            $table->unsignedSmallInteger('grace_checkout_after_minutes')->default(0)->after('grace_checkout_before_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('office_shifts', function (Blueprint $table) {
            $table->dropColumn(['grace_checkin_before_minutes', 'grace_checkin_after_minutes', 'grace_checkout_before_minutes', 'grace_checkout_after_minutes']);
        });
    }
};
