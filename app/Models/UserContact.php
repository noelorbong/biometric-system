<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserContact extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;
    use \App\Traits\UserAuditTrait;

    protected $fillable = [
        'user_id',
        'type',
        'value',
        'is_primary',
        'user_add',
        'user_last_modify',

    ];

    protected $casts = [
        'is_primary' => 'boolean',
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

    // Helper
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }
}