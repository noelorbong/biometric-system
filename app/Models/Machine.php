<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;

    protected $table = 'machines';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'MachineAlias',
        'ConnectType',
        'IP',
        'SerialPort',
        'Port',
        'Baudrate',
        'MachineNumber',
        'IsHost',
        'Enabled',
        'CommPassword',
        'UILanguage',
        'DateFormat',
        'InOutRecordWarn',
        'Idle',
        'Voice',
        'managercount',
        'usercount',
        'fingercount',
        'SecretCount',
        'FirmwareVersion',
        'ProductType',
        'LockControl',
        'Purpose',
        'ProduceKind',
        'sn',
        'PhotoStamp',
        'IsIfChangeConfigServer2',
        'pushver',
        'IsAndroid',
        'AutoDownload',
        'AutoDownloadInterval',
        'AutoDownloadUserFilter',
        'AutoDownloadLastSyncedAt',
    ];

    protected $casts = [
        'Port' => 'integer',
        'Baudrate' => 'integer',
        'MachineNumber' => 'integer',
        'IsHost' => 'boolean',
        'Enabled' => 'boolean',
        'InOutRecordWarn' => 'integer',
        'Idle' => 'integer',
        'Voice' => 'integer',
        'managercount' => 'integer',
        'usercount' => 'integer',
        'fingercount' => 'integer',
        'SecretCount' => 'integer',
        'PhotoStamp' => 'boolean',
        'IsIfChangeConfigServer2' => 'boolean',
        'IsAndroid' => 'boolean',
        'AutoDownload' => 'boolean',
        'AutoDownloadInterval' => 'integer',
        'AutoDownloadLastSyncedAt' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
