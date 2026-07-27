<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('in_charge_user_id')
                ->nullable()
                ->after('office_shift_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('in_charge_enabled')
                ->default(false)
                ->after('in_charge_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('in_charge_user_id');
            $table->dropColumn('in_charge_enabled');
        });
    }
};