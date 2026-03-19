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
    ];

    protected $casts = [
        'is_flexible' => 'boolean',
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
