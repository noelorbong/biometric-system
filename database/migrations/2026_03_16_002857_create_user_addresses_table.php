<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('label')->nullable(); // home, work, billing
            $table->string('address1');
            $table->string('address2')->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('zipcode')->nullable();
            $table->boolean('is_primary')->default(false);

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
        Schema::dropIfExists('user_addresses');
    }
};