<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('userinfo', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('USERID')->unique();
            $table->string('Badgenumber')->nullable();
            $table->string('SSN')->nullable();
            $table->string('Name')->nullable();
            $table->string('Gender')->nullable();
            $table->string('TITLE')->nullable();
            $table->string('PAGER')->nullable();
            $table->dateTime('BIRTHDAY')->nullable();
            $table->dateTime('HIREDDAY')->nullable();
            $table->string('street')->nullable();
            $table->string('CITY')->nullable();
            $table->string('STATE')->nullable();
            $table->string('ZIP')->nullable();
            $table->string('OPHONE')->nullable();
            $table->string('FPHONE')->nullable();
            $table->integer('VERIFICATIONMETHOD')->nullable();
            $table->integer('DEFAULTDEPTID')->nullable();
            $table->integer('SECURITYFLAGS')->nullable();
            $table->integer('ATT')->nullable();
            $table->integer('INLATE')->nullable();
            $table->integer('OUTEARLY')->nullable();
            $table->integer('OVERTIME')->nullable();
            $table->integer('SEP')->nullable();
            $table->integer('HOLIDAY')->nullable();
            $table->string('MINZU')->nullable();
            $table->string('PASSWORD')->nullable();
            $table->integer('LUNCHDURATION')->nullable();
            $table->longText('PHOTO')->nullable();
            $table->string('mverifypass')->nullable();
            $table->longText('Notes')->nullable();
            $table->integer('privilege')->nullable();
            $table->integer('InheritDeptSch')->nullable();
            $table->integer('InheritDeptSchClass')->nullable();
            $table->integer('AutoSchPlan')->nullable();
            $table->integer('MinAutoSchInterval')->nullable();
            $table->integer('RegisterOT')->nullable();
            $table->integer('InheritDeptRule')->nullable();
            $table->integer('EMPRIVILEGE')->nullable();
            $table->string('CardNo')->nullable();
            $table->integer('FaceGroup')->nullable();
            $table->integer('AccGroup')->nullable();
            $table->integer('UseAccGroupTZ')->nullable();
            $table->integer('VerifyCode')->nullable();
            $table->integer('Expires')->nullable();
            $table->integer('ValidCount')->nullable();
            $table->dateTime('ValidTimeBegin')->nullable();
            $table->dateTime('ValidTimeEnd')->nullable();
            $table->integer('TimeZone1')->nullable();
            $table->integer('TimeZone2')->nullable();
            $table->integer('TimeZone3')->nullable();
            $table->string('IDCardNo')->nullable();
            $table->string('IDCardValidTime')->nullable();

            $table->integer('user_add')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

            $table->integer('user_last_modify')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('userinfo');
    }
};
