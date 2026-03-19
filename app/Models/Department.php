<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;
    use \App\Traits\UserAuditTrait;

    protected $fillable = [
        'department_name',
        'dep_long',
        'dep_short',
        'status',
        'user_add',
        'user_last_modify',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'user_add');
    }

    public function modifiedBy()
    {
        return $this->belongsTo(User::class, 'user_last_modify');
    }
}
