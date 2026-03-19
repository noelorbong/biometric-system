<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserProfile extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;
    use \App\Traits\UserAuditTrait;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'dob',
        'gender',
        'image',
        'thumbnail',
        'user_add',
        'user_last_modify',
    ];

    protected $casts = [
        'dob' => 'date',
    ];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'user_add');
    }

    public function modifiedBy()
    {
        return $this->belongsTo(User::class, 'user_last_modify');
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper / Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail ? asset('storage/profiles/' . $this->thumbnail) : asset('images/default-avatar.png');
    }
}