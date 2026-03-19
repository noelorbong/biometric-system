<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('template')) {
            return;
        }

        Schema::create('template', function (Blueprint $table) {
            $table->increments('TEMPLATEID');
            $table->unsignedInteger('USERID')->nullable()->index();
            $table->unsignedTinyInteger('FINGERID')->nullable()->index();
            $table->binary('TEMPLATE')->nullable();
            $table->binary('TEMPLATE1')->nullable();
            $table->binary('TEMPLATE2')->nullable();
            $table->binary('TEMPLATE3')->nullable();
            $table->binary('TEMPLATE4')->nullable();
            $table->binary('BITMAPPICTURE')->nullable();
            $table->binary('BITMAPPICTURE2')->nullable();
            $table->binary('BITMAPPICTURE3')->nullable();
            $table->binary('BITMAPPICTURE4')->nullable();
            $table->unsignedTinyInteger('USETYPE')->nullable();
            $table->string('EMACHINENUM', 100)->nullable()->index();
            $table->integer('Flag')->nullable();
            $table->integer('DivisionFP')->nullable();

            $table->unique(['USERID', 'FINGERID', 'EMACHINENUM'], 'template_user_finger_machine_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template');
    }
};
