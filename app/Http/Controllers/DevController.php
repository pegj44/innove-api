<?php

namespace App\Http\Controllers;

use App\Events\UnitsEvent;
use App\Models\TradeHistoryV3Model;
use App\Models\TradeReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DevController extends Controller
{
    public function auditAccount(Request $request)
    {
        $file = $request->file('file');

        $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $item = \App\Models\TradingAccountCredential::with('historyV3')
            ->where('account_id', auth()->user()->account_id)
            ->where('funder_account_id', $originalFileName)
            ->first();

        if (empty($item)) {
            return 'no data';
        }
!d($item);
die();
        if (!empty($item['history_v3'])) {

            foreach ($item['history_v3'] as $historyItem) {
                $date = Carbon::parse($historyItem['created_at'])->format('Y-n-j');
                $date = Carbon::createFromFormat('Y-m-d', $date);

                if ($date->lessThan(Carbon::today())) {
                    $date = $date->toDateString();
                    $pnl = (float) $historyItem['latest_equity'] - (float) $historyItem['starting_daily_equity'];
                    if ($pnl != 0) {
                        $dates[$date] = $pnl;
                    }
                }
            }
        }

        $itemId = $item['id'];
        $dataArray = [];

        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ',');

            $highestBal = 0;
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $headers = array_map('strtolower', $headers);
                $data = array_combine($headers, $row);

                $balance = (float) $data['balance'];

                if ($balance > $highestBal) {
                    $highestBal = $balance;
                }

                $startingBal = $balance - (float) $data['pnl'];
                $dataArray[] = [
                    'trade_account_credential_id' => $itemId,
                    'starting_daily_equity' => $startingBal,
                    'latest_equity' => $balance,
                    'highest_balance' => $highestBal,
                    'created_at' => $data['date'],
                    'updated_at' => $data['date']
                ];

//                !d(array_combine($headers, $row));

            }
            fclose($handle);
        }

//            $difference = array
        !d($dataArray);

        TradeHistoryV3Model::insert($dataArray);

die();


        // Return the structured array
        return response()->json($dataArray);
    }

    public function initiateTrade(Request $request)
    {
        $ids = $request->get('ids');
        $items = TradeReport::with(['tradingAccountCredential.userAccount.funderAccountCredential', 'tradingAccountCredential.userAccount.tradingUnit'])
            ->whereIn('id', $ids)
            ->where('account_id', auth()->user()->account_id)
            ->get();

        if (empty($items)) {
            return response()->json(['No items found']);
        }

        $pairId = '';
        $purchase_type = (!empty($request->get('purchase_type')))? $request->get('purchase_type') : 'buy';
        $symbol = (!empty($request->get('symbol')))? $request->get('symbol') : '/MGCZ24';
        $order_amount = (!empty($request->get('order_amount')))? $request->get('order_amount'): 1;
        $take_profit_ticks = (!empty($request->get('take_profit_ticks')))? $request->get('take_profit_ticks'): 31;
        $stop_loss_ticks = (!empty($request->get('stop_loss_ticks')))? $request->get('stop_loss_ticks'): 33;

        $uuid = (string) Str::uuid();
        $currentDateTime = Carbon::now()->format('Ymd_His');
        $queueId = $uuid . '_' . $currentDateTime;
        $machine = $request->get('machine');


        foreach ($items as $item) {

            $credential = getFunderAccountCredential($item);
            $tradeData = [
                'pairQueueId' => $pairId,
                'account_id' => $item->tradingAccountCredential->funder_account_id,
                'latest_equity' => $item->latest_equity,
                'purchase_type' => $purchase_type,
                'symbol' => $symbol,
                'order_amount' => $order_amount,
                'take_profit_ticks' => $take_profit_ticks,
                'stop_loss_ticks' => $stop_loss_ticks,
                'queue_id' => $queueId,
                'machine' => $machine,
                'unit' => $item->tradingAccountCredential->userAccount->tradingUnit->unit_id,
                'itemId' => $item['id'],
                'loginUsername' => $credential['loginUsername'],
                'loginPassword' => $credential['loginPassword']
            ];

            UnitsEvent::dispatch(getUnitAuthId(), $tradeData, 'initiate-trade', $machine, $item->tradingAccountCredential->userAccount->tradingUnit->unit_id);

            break;
        }

        die();
    }
}
