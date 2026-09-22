<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkSuspension extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    public function officeShift() { return $this->belongsTo(OfficeShift::class); }
}
