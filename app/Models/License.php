<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $table = 'license';

    protected $fillable = [
        'license_key',
        'license_id',
        'machine_id',
        'machine_fingerprint',
        'trial_started_at',
        'license_expiry',
    ];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'license_expiry'   => 'datetime',
    ];
}
