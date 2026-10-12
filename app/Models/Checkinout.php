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

    public const OUT_VARIANTS = ['O', '0', 'OUT'];
    public const IN_VARIANTS = ['I', '1', 'IN'];

    // Raw device data mixes '0' and 'O' for check-out; expose one canonical value.
    public function getCHECKTYPEAttribute($value)
    {
        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, self::OUT_VARIANTS, true)) {
            return 'O';
        }

        if (in_array($normalized, self::IN_VARIANTS, true)) {
            return 'I';
        }

        return $value;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'USERID', 'id');
    }
}
