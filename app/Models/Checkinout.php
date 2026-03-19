<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Checkinout extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;

    protected $table = 'checkinout';
    public $timestamps = false;

    protected $fillable = [
        'USERID',
        'CHECKTIME',
        'CHECKTYPE',
        'VERIFYCODE',
        'SENSORID',
        'Memoinfo',
        'WorkCode',
        'sn',
        'UserExtFmt',
    ];

    protected $casts = [
        'CHECKTIME' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'USERID', 'id');
    }
}
