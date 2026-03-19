<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OfficeShiftSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'office_shift_id',
        'sequence',
        'time_in',
        'time_out',
        'is_next_day',
    ];

    protected $casts = [
        'is_next_day' => 'boolean',
    ];

    public function officeShift()
    {
        return $this->belongsTo(OfficeShift::class, 'office_shift_id');
    }
}
