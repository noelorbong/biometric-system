<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;
    use \App\Traits\LogsActivity;
    use \App\Traits\UserAuditTrait;

    // Mass assignable fields
    protected $fillable = [
        'name',
        'avatar',
        'email',
        'password',
        'role',
        'status',
        'department',
        'department_id',
        'college_id',
        'office_shift_id',
        'main_account',
        'last_activity',
        'last_login',
        'last_ip',
        'user_agent',
        'user_add',
        'user_last_modify',
    ];

    // Hidden fields in JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Attribute casting
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_activity' => 'datetime',
        'last_login' => 'datetime',
        'main_account' => 'boolean',
        'status' => 'boolean',
    ];

    // =========================
    // Relationships
    // =========================

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'user_add');
    }

    public function modifiedBy()
    {
        return $this->belongsTo(User::class, 'user_last_modify');
    }

    // Optional: Related profile table if you implement it
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function contacts()
    {
        return $this->hasMany(UserContact::class);
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }

    public function biometricInfo()
    {
        return $this->hasOne(UserBiometricInfo::class, 'USERID', 'id');
    }

    public function officeShift()
    {
        return $this->belongsTo(OfficeShift::class, 'office_shift_id');
    }

    public function departmentRef()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function collegeRef()
    {
        return $this->belongsTo(College::class, 'college_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public function isAdmin(): bool
    {
        return $this->role === 1;
    }

    // Automatically hash password when setting it
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
        }
    }

    // Get avatar URL (fallback to default)
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar ? asset('storage/avatars/' . $this->avatar) : asset('images/default-avatar.png');
    }
}