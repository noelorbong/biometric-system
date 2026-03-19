<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_name');
            $table->string('dep_long')->nullable();
            $table->string('dep_short')->nullable();
            $table->boolean('status')->default(true);

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
        Schema::dropIfExists('departments');
    }
};
