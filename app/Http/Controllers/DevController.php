<?php

namespace App\Http\Controllers;

use App\Events\UnitsEvent;
use App\Models\TradeReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DevController extends Controller
{
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
