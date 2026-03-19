<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait UserAuditTrait
{
    /**
     * This method is called automatically by Laravel when the model boots.
     * It must be named "boot" + "TraitName".
     */
    protected static function bootUserAuditTrait()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                // Ensure the column exists before trying to set it
                $model->user_add = Auth::id();
                $model->user_last_modify = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->user_last_modify = Auth::id();
            }
        });
    }
}