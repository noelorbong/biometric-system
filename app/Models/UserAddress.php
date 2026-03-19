<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserAddress extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Traits\LogsActivity;
    use \App\Traits\UserAuditTrait;

    protected $fillable = [
        'user_id',
        'label',
        'address1',
        'address2',
        'barangay',
        'municipality',
        'province',
        'zipcode',
        'is_primary',
        'user_add',
        'user_last_modify',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

     public function addedBy()
    {
        return $this->belongsTo(User::class, 'user_add');
    }

    public function modifiedBy()
    {
        return $this->belongsTo(User::class, 'user_last_modify');
    }


    // Helper
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    public function fullAddress(): string
    {
        return trim("{$this->address1} {$this->address2}, {$this->barangay}, {$this->municipality}, {$this->province} {$this->zipcode}");
    }
}