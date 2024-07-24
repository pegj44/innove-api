<?php

namespace App\Observers;

use App\Models\Funder;

class FunderObserver
{
    /**
     * Handle the Funder "created" event.
     */
    public function created(Funder $funder): void
    {
        $funder->createMeta($funder);
    }

    /**
     * Handle the Funder "updated" event.
     */
    public function updated(Funder $funder): void
    {

    }

    /**
     * Handle the Funder "deleted" event.
     */
    public function deleted(Funder $funder): void
    {
        //
    }

    /**
     * Handle the Funder "restored" event.
     */
    public function restored(Funder $funder): void
    {
        //
    }

    /**
     * Handle the Funder "force deleted" event.
     */
    public function forceDeleted(Funder $funder): void
    {
        //
    }
}
