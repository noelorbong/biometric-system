<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('license', function (Blueprint $table) {
            $table->id();
            $table->string('license_key')->nullable();
            $table->string('license_id')->nullable();
            $table->string('machine_id')->nullable();
            $table->string('machine_fingerprint')->nullable();
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license');
    }
};
