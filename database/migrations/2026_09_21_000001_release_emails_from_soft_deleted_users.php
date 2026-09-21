<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->orderBy('id')
            ->each(function (object $user): void {
                $suffix = "__deleted_{$user->id}";
                $email = substr($user->email, 0, 255 - strlen($suffix)) . $suffix;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => $email,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Original emails cannot be recovered once the tombstone suffix is applied.
    }
};