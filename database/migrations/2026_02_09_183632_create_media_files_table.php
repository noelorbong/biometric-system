<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();

            $table->string('file_type')->nullable();
            $table->string('file_extension', 20);
            $table->string('original_file_name');
            $table->string('thumbnail')->nullable();
            $table->string('file_name'); // stored filename
            $table->unsignedBigInteger('file_size');
            $table->string('status')->nullable();

            $table->foreignId('user_add')->nullable()->references('id')->on('users');
            $table->foreignId('user_last_modify')->nullable()->references('id')->on('users');

            $table->timestamps();
            $table->softDeletes();

            // (Optional) FKs if you have users table
            // $table->foreign('user_add')->references('id')->on('users')->nullOnDelete();
            // $table->foreign('user_last_modify')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};

