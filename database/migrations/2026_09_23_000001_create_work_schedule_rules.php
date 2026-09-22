<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('profile');
            $table->string('reason', 1000);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['office_shift_id', 'effective_from', 'effective_to'], 'schedule_rule_dates');
        });
        Schema::create('work_suspensions', function (Blueprint $table) {
            $table->id();
            $table->date('suspension_date');
            $table->string('name', 120);
            $table->string('duration', 20)->default('full_day');
            $table->foreignId('office_shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('suspension_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_suspensions');
        Schema::dropIfExists('work_schedule_rules');
    }
};
