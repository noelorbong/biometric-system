<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class MediaFile extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'file_type',
        'file_extension',
        'original_file_name',
        'file_name',
        'file_size',
        'thumbnail',
        'status',
        'user_add',
        'user_last_modify',
    ];
}

