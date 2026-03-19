<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('schedule')->nullable();
            $table->boolean('is_flexible')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('office_shifts')->insert([
            [
                'name' => 'Regular',
                'schedule' => '8:00AM-12:00NN, 1:00PM-5:00PM',
                'is_flexible' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Guard',
                'schedule' => '10:00PM-6:00AM, 6:00AM-10:00PM',
                'is_flexible' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Utility',
                'schedule' => '5:00AM-8:00AM, 9:00AM-11:00AM, 1:00PM-5:00PM',
                'is_flexible' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Part-Timer',
                'schedule' => 'Flexible Time',
                'is_flexible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('office_shifts');
    }
};
