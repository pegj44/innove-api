<?php

namespace App\Console\Commands;

use App\Models\TradeHistoryV3Model;
use Carbon\Carbon;
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
        $tradingHistoryArr = [];

        $reports = TradeReport::with('tradingAccountCredential.historyV3')
            ->whereColumn('starting_daily_equity', '!=', 'latest_equity')
            ->get();

        foreach ($reports as $report) {
            $startingDailyEquity = $report->starting_daily_equity;
            $report->starting_daily_equity = $report->latest_equity;
            $report->n_trades = 0;

            if ($report->status === 'abstained') {
                $report->status = 'idle';
            }

            $report->save();

            $highestbal = (float) $report->tradingAccountCredential->historyV3->max('highest_balance');

            $tradingHistoryArr[] = [
                'trade_account_credential_id' => $report->trade_account_credential_id,
                'starting_daily_equity' => (float) $startingDailyEquity,
                'latest_equity' => $report->latest_equity,
                'status' => $report->tradingAccountCredential->current_phase,
                'highest_balance' => ($report->latest_equity > $highestbal)? $report->latest_equity : $highestbal,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        TradeHistoryV3Model::insert($tradingHistoryArr);

        $this->info('Trade reports updated successfully.');
    }
}
