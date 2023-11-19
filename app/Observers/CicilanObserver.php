<?php

namespace App\Observers;

use App\Models\Cicilan;

class CicilanObserver
{
    /**
     * Handle the Cicilan "created" event.
     */
    public function created(Cicilan $cicilan): void
    {
        //
    }

    /**
     * Handle the Cicilan "updated" event.
     */
    public function updated(Cicilan $cicilan): void
    {
        //
    }

    /**
     * Handle the Cicilan "deleted" event.
     */
    public function deleted(Cicilan $cicilan): void
    {
        //
    }

    /**
     * Handle the Cicilan "restored" event.
     */
    public function restored(Cicilan $cicilan): void
    {
        //
    }

    /**
     * Handle the Cicilan "force deleted" event.
     */
    public function forceDeleted(Cicilan $cicilan): void
    {
        //
    }
}
