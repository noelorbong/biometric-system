<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
     public function deleted(User $user)
    {
        // soft delete related tables
        $user->profile()->delete();
        $user->contacts()->delete();
        $user->addresses()->delete();
    }

    /**
     * Handle the User "restored" event.
     */
    public function restoring(User $user)
    {
        $user->profile()->withTrashed()->restore();
        $user->contacts()->withTrashed()->restore();
        $user->addresses()->withTrashed()->restore();
    }

    /**
     * Handle the User "force deleted" event.
     */
      public function forceDeleted(User $user)
    {
        // permanently delete related tables
        $user->profile()->forceDelete();
        $user->contacts()->forceDelete();
        $user->addresses()->forceDelete();
    }
}
