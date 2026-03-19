<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_shift_id')->constrained('office_shifts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->time('time_in');
            $table->time('time_out');
            $table->boolean('is_next_day')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['office_shift_id', 'sequence']);
        });

        $regularId = DB::table('office_shifts')->where('name', 'Regular')->value('id');
        $guardId = DB::table('office_shifts')->where('name', 'Guard')->value('id');
        $utilityId = DB::table('office_shifts')->where('name', 'Utility')->value('id');

        if ($regularId) {
            DB::table('office_shift_schedules')->insert([
                [
                    'office_shift_id' => $regularId,
                    'sequence' => 1,
                    'time_in' => '08:00:00',
                    'time_out' => '12:00:00',
                    'is_next_day' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'office_shift_id' => $regularId,
                    'sequence' => 2,
                    'time_in' => '13:00:00',
                    'time_out' => '17:00:00',
                    'is_next_day' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if ($guardId) {
            DB::table('office_shift_schedules')->insert([
                [
                    'office_shift_id' => $guardId,
                    'sequence' => 1,
                    'time_in' => '22:00:00',
                    'time_out' => '06:00:00',
                    'is_next_day' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'office_shift_id' => $guardId,
                    'sequence' => 2,
                    'time_in' => '06:00:00',
                    'time_out' => '22:00:00',
                    'is_next_day' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if ($utilityId) {
            DB::table('office_shift_schedules')->insert([
                [
                    'office_shift_id' => $utilityId,
                    'sequence' => 1,
                    'time_in' => '05:00:00',
                    'time_out' => '08:00:00',
                    'is_next_day' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'office_shift_id' => $utilityId,
                    'sequence' => 2,
                    'time_in' => '09:00:00',
                    'time_out' => '11:00:00',
                    'is_next_day' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'office_shift_id' => $utilityId,
                    'sequence' => 3,
                    'time_in' => '13:00:00',
                    'time_out' => '17:00:00',
                    'is_next_day' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('office_shift_schedules');
    }
};
