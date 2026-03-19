<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('name_extension')->nullable();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->string('image')->nullable();
            $table->string('thumbnail')->nullable();

            // Audit
            $table->foreignId('user_add')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

            $table->foreignId('user_last_modify')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
