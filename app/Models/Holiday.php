<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;
    use \App\Traits\UserAuditTrait;

    protected $fillable = [
        'name',
        'holiday_date',
        'type',
        'duration',
        'is_working_day',
        'notes',
        'user_add',
        'user_last_modify',
    ];

    protected $casts = [
        'holiday_date' => 'date:Y-m-d',
        'is_working_day' => 'boolean',
    ];
}