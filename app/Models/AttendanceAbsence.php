<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceAbsence extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'absence_date', 'status', 'duration', 'leave_type', 'remarks', 'created_by', 'updated_by'];

    protected $casts = ['absence_date' => 'date:Y-m-d'];
}