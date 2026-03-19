<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserBiometricInfo extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;
    use \App\Traits\UserAuditTrait;

    protected $table = 'userinfo';
    public $timestamps = false;

    protected $fillable = [
        'USERID',
        'Badgenumber',
        'SSN',
        'Name',
        'Gender',
        'TITLE',
        'PAGER',
        'BIRTHDAY',
        'HIREDDAY',
        'street',
        'CITY',
        'STATE',
        'ZIP',
        'OPHONE',
        'FPHONE',
        'VERIFICATIONMETHOD',
        'DEFAULTDEPTID',
        'SECURITYFLAGS',
        'ATT',
        'INLATE',
        'OUTEARLY',
        'OVERTIME',
        'SEP',
        'HOLIDAY',
        'MINZU',
        'PASSWORD',
        'LUNCHDURATION',
        'PHOTO',
        'mverifypass',
        'Notes',
        'privilege',
        'InheritDeptSch',
        'InheritDeptSchClass',
        'AutoSchPlan',
        'MinAutoSchInterval',
        'RegisterOT',
        'InheritDeptRule',
        'EMPRIVILEGE',
        'CardNo',
        'FaceGroup',
        'AccGroup',
        'UseAccGroupTZ',
        'VerifyCode',
        'Expires',
        'ValidCount',
        'ValidTimeBegin',
        'ValidTimeEnd',
        'TimeZone1',
        'TimeZone2',
        'TimeZone3',
        'IDCardNo',
        'IDCardValidTime',
        'user_add',
        'user_last_modify',
    ];

    protected $casts = [
        'BIRTHDAY' => 'datetime',
        'HIREDDAY' => 'datetime',
        'ValidTimeBegin' => 'datetime',
        'ValidTimeEnd' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'USERID', 'id');
    }
}
