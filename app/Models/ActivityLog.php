<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'model_name', 'model_id', 'before', 'after', 'ip_address'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];
}
