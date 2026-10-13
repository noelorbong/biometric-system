<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OfficeShift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'schedule',
        'is_flexible',
        'grace_enabled',
        'grace_before_minutes',
        'grace_after_minutes',
        'grace_checkin_before_minutes', 'grace_checkin_after_minutes',
        'grace_checkout_before_minutes', 'grace_checkout_after_minutes',
        'enable_auto_correction',
    ];

    protected $casts = [
        'is_flexible' => 'boolean',
        'grace_enabled' => 'boolean',
        'grace_before_minutes' => 'integer',
        'grace_after_minutes' => 'integer',
        'grace_checkin_before_minutes' => 'integer', 'grace_checkin_after_minutes' => 'integer',
        'grace_checkout_before_minutes' => 'integer', 'grace_checkout_after_minutes' => 'integer',
        'enable_auto_correction' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'office_shift_id');
    }

    public function schedules()
    {
        return $this->hasMany(OfficeShiftSchedule::class, 'office_shift_id')->orderBy('sequence');
    }
}
