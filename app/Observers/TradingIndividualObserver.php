<?php

namespace App\Observers;

use App\Models\TradingIndividual;

class TradingIndividualObserver
{
    public function saving(TradingIndividual $tradingIndividual): void
    {
    }

    public function creating(TradingIndividual $tradingIndividual): void
    {
    }

    /**
     * Handle the TradingIndividual "created" event.
     */
    public function created(TradingIndividual $tradingIndividual): void
    {
        $tradingIndividual->createMeta($tradingIndividual);
    }

    /**
     * Handle the TradingIndividual "updated" event.
     */
    public function updated(TradingIndividual $tradingIndividual): void
    {

    }

    /**
     * Handle the TradingIndividual "deleted" event.
     */
    public function deleted(TradingIndividual $tradingIndividual): void
    {
        //
    }

    /**
     * Handle the TradingIndividual "restored" event.
     */
    public function restored(TradingIndividual $tradingIndividual): void
    {
        //
    }

    /**
     * Handle the TradingIndividual "force deleted" event.
     */
    public function forceDeleted(TradingIndividual $tradingIndividual): void
    {
        //
    }
}
