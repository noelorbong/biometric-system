<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkScheduleRule extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['profile' => 'array'];
    public function officeShift() { return $this->belongsTo(OfficeShift::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
