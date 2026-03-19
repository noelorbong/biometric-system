<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppSetting extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;
    use \App\Traits\UserAuditTrait;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'user_add',
        'user_last_modify',
    ];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'user_add');
    }

    public function modifiedBy()
    {
        return $this->belongsTo(User::class, 'user_last_modify');
    }
}
