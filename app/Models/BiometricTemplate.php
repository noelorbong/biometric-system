<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiometricTemplate extends Model
{
    use HasFactory;

    protected $table = 'template';
    protected $primaryKey = 'TEMPLATEID';
    public $timestamps = false;

    protected $fillable = [
        'USERID',
        'FINGERID',
        'TEMPLATE',
        'TEMPLATE1',
        'TEMPLATE2',
        'TEMPLATE3',
        'TEMPLATE4',
        'BITMAPPICTURE',
        'BITMAPPICTURE2',
        'BITMAPPICTURE3',
        'BITMAPPICTURE4',
        'USETYPE',
        'EMACHINENUM',
        'Flag',
        'DivisionFP',
    ];

    protected $casts = [
        'USERID' => 'integer',
        'FINGERID' => 'integer',
        'USETYPE' => 'integer',
        'Flag' => 'integer',
        'DivisionFP' => 'integer',
    ];
}
