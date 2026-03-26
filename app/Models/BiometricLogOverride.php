<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BiometricLogOverride extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'biometric_log_overrides';

    protected $fillable = [
        'user_id',
        'checkinout_id',
        'action_type',
        'old_checktime',
        'old_checktype',
        'new_checktime',
        'new_checktype',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'old_checktime' => 'datetime',
        'new_checktime' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function checkinout()
    {
        return $this->belongsTo(Checkinout::class, 'checkinout_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
