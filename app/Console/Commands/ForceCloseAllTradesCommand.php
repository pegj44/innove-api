<?php

namespace App\Console\Commands;

use App\Events\UnitsEvent;
use App\Models\TradeHistoryV3Model;
use App\Models\TradeQueueModel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\TradeReport;

class ForceCloseAllTradesCommand extends Command
{
    protected $signature = 'trade-report:close-all-trades';
    protected $description = 'Close all running trades';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $queues = TradeQueueModel::where('status', 'trading')->get();
        $currentDateTime = Carbon::now('Asia/Manila');

        foreach ($queues as $item) {
            $data = maybe_unserialize($item['data']);
            $unitAccountId = \App\Models\UserUnitLogin::where('account_id', $item->account_id)->pluck('unit_user_id')->first();

            foreach ($data as $id => $tradeItem) {
                UnitsEvent::dispatch($unitAccountId, [
                    'queue_id' => $item->id,
                    'itemId' => $id,
                    'dateTime' => $currentDateTime->format('F j, Y g:i A'),
                    'account_id' => $tradeItem['funder_account_id_long']
                ], 'close-position', $tradeItem['platform_type'], $tradeItem['unit_id']);
            }

            sleep(5); // add delay
        }

        $this->info('All trades are now closed.');
    }
}
