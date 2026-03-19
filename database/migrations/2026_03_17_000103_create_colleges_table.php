<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('colleges', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id')->default(1);
            $table->string('college_short', 20)->nullable();
            $table->string('college_long', 50)->nullable();
            $table->string('college_head', 100)->nullable();
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
        Schema::dropIfExists('colleges');
    }
};
