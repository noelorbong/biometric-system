<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('users')->whereNull('email')->exists()) {
            throw new \RuntimeException('Assign emails to all users before reverting optional emails.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
