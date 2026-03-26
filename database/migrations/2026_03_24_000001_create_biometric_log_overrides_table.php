<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('biometric_log_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('checkinout_id')->nullable()->index();
            $table->string('action_type', 20); // add | override
            $table->dateTime('old_checktime')->nullable();
            $table->string('old_checktype', 10)->nullable();
            $table->dateTime('new_checktime');
            $table->string('new_checktype', 10);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'new_checktime'], 'bio_override_user_new_time_idx');
            $table->index(['user_id', 'old_checktime'], 'bio_override_user_old_time_idx');
            $table->index(['user_id', 'action_type'], 'bio_override_user_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_log_overrides');
    }
};
