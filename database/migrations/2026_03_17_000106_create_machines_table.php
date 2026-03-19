<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('MachineAlias')->nullable();
            $table->string('ConnectType', 50)->nullable();
            $table->string('IP', 45)->nullable();
            $table->string('SerialPort', 50)->nullable();
            $table->unsignedInteger('Port')->nullable();
            $table->unsignedInteger('Baudrate')->nullable();
            $table->unsignedInteger('MachineNumber')->nullable();
            $table->boolean('IsHost')->default(false);
            $table->boolean('Enabled')->default(true);
            $table->string('CommPassword', 100)->nullable();
            $table->string('UILanguage', 50)->nullable();
            $table->string('DateFormat', 50)->nullable();
            $table->unsignedInteger('InOutRecordWarn')->nullable();
            $table->unsignedInteger('Idle')->nullable();
            $table->unsignedInteger('Voice')->nullable();
            $table->unsignedInteger('managercount')->default(0);
            $table->unsignedInteger('usercount')->default(0);
            $table->unsignedInteger('fingercount')->default(0);
            $table->unsignedInteger('SecretCount')->default(0);
            $table->string('FirmwareVersion', 100)->nullable();
            $table->string('ProductType', 100)->nullable();
            $table->string('LockControl', 50)->nullable();
            $table->string('Purpose', 100)->nullable();
            $table->string('ProduceKind', 100)->nullable();
            $table->string('sn', 100)->nullable();
            $table->boolean('PhotoStamp')->default(false);
            $table->boolean('IsIfChangeConfigServer2')->default(false);
            $table->string('pushver', 50)->nullable();
            $table->boolean('IsAndroid')->default(false);
            $table->softDeletes();

            $table->index('sn');
            $table->index('IP');
            $table->index('MachineNumber');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
