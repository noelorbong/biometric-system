<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->string('AutoDownloadUserFilter', 20)->default('existing')->after('AutoDownloadInterval');
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('AutoDownloadUserFilter');
        });
    }
};
