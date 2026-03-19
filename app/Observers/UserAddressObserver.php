<?php

namespace App\Observers;

use App\Models\UserAddress;

class UserAddressObserver
{
    /**
     * Handle the UserAddress "created" event.
     */
    public function created(UserAddress $userAddress): void
    {
        //
    }

    /**
     * Handle the UserAddress "updated" event.
     */
    public function updated(UserAddress $userAddress): void
    {
        //
    }

    /**
     * Handle the UserAddress "deleted" event.
     */
    public function deleted(UserAddress $userAddress): void
    {
        //
    }

    /**
     * Handle the UserAddress "restored" event.
     */
    public function restored(UserAddress $userAddress): void
    {
        //
    }

    /**
     * Handle the UserAddress "force deleted" event.
     */
    public function forceDeleted(UserAddress $userAddress): void
    {
        //
    }
}
