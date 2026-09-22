<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('holidays')) {
            return;
        }

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->date('holiday_date')->unique();
            $table->enum('type', ['regular', 'special_non_working', 'special_working'])->default('regular');
            $table->enum('duration', ['full_day', 'morning', 'afternoon'])->default('full_day');
            $table->boolean('is_working_day')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('user_add')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_last_modify')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};