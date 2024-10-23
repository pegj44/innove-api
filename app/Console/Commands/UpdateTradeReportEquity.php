<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TradeReport;

class UpdateTradeReportEquity extends Command
{
    protected $signature = 'trade-report:update-equity';
    protected $description = 'Update starting_daily_equity and status fields for Trade Reports';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        TradeReport::whereColumn('starting_daily_equity', '!=', 'latest_equity')
            ->chunkById(500, function ($reports)
            {
                foreach ($reports as $report) {
                    $report->starting_daily_equity = $report->latest_equity;

                    if ($report->status === 'abstained') {
                        $report->status = 'idle';
                    }

                    $report->save();
                }
            });

        $this->info('Trade reports updated successfully.');
    }
}
