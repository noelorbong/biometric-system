<?php

namespace App\Observers;

use App\Models\UserContact;

class UserContactObserver
{
    /**
     * Handle the UserContact "created" event.
     */
    public function created(UserContact $userContact): void
    {
        //
    }

    /**
     * Handle the UserContact "updated" event.
     */
    public function updated(UserContact $userContact): void
    {
        //
    }

    /**
     * Handle the UserContact "deleted" event.
     */
    public function deleted(UserContact $userContact): void
    {
        //
    }

    /**
     * Handle the UserContact "restored" event.
     */
    public function restored(UserContact $userContact): void
    {
        //
    }

    /**
     * Handle the UserContact "force deleted" event.
     */
    public function forceDeleted(UserContact $userContact): void
    {
        //
    }
}
