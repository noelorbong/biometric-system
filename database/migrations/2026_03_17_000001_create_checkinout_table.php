<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('checkinout', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('USERID');
            $table->dateTime('CHECKTIME');
            $table->string('CHECKTYPE')->nullable();
            $table->integer('VERIFYCODE')->nullable();
            $table->string('SENSORID')->nullable();
            $table->string('Memoinfo')->nullable();
            $table->string('WorkCode')->nullable();
            $table->string('sn')->nullable();
            $table->integer('UserExtFmt')->nullable();
            $table->unique(['USERID', 'CHECKTIME']);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkinout');
    }
};
