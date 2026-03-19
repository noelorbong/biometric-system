<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
                $table->id();

                // Auth
                $table->string('email')->unique();
                $table->string('password');
                $table->string('name');
                $table->string('avatar')->nullable(); // small thumbnail
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();

                // Role & status
                $table->unsignedTinyInteger('role')->default(0); 
                // 0=user, 1=admin, etc (or replace with role_id later)

                $table->boolean('main_account')->default(false);
                $table->boolean('status')->default(true);

                // Activity logs
                $table->timestamp('last_activity')->nullable();
                $table->timestamp('last_login')->nullable();
                $table->string('last_ip')->nullable();
                $table->text('user_agent')->nullable();

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

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

          DB::table('users')->insert([
            'name' => 'Admin, User',
            'email' => 'admin@gmail.com',
            'role' => 1,
            'status' => 1,
            'main_account' =>1,
            'password' => Hash::make('aq1sw2de3'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
