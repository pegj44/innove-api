<?php

namespace App\Jobs;

use App\Http\Controllers\TradingIndividualsController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TradeIndividualAddRowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $item;
    protected $units;
    protected $user_id;

    /**
     * Create a new job instance.
     */
    public function __construct($user_id, $item, $units)
    {
        $this->user_id = $user_id;
        $this->item = $item;
        $this->units = $units;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        TradingIndividualsController::uploadRowItem($this->user_id, $this->item, $this->units);
    }
}
