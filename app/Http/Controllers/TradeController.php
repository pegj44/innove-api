<?php

namespace App\Http\Controllers;

use App\Events\UnitResponse;
use App\Events\UnitsEvent;
use App\Models\PairedItems;
use App\Models\TradeHistoryModel;
use App\Models\TradeReport;
use App\Models\TradingIndividual;
use App\Models\TradingUnitQueueModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TradeController extends Controller
{
    public $stopLossPips = 5.1;
    public $takeProfitPips = 4.9;
    public $mandatoryStopLossPercentage = 0.02;
    public $volumeMultiplierPercentage = 0.8;

    public function closePosition(Request $request)
    {
        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $request->get('pairUnit'));

        if ($isUnitConnected) {
            $UnitMatch = TradingUnitQueueModel::where('account_id', auth()->user()->account_id)
                ->where('queue_id', $request->get('queue_id'))->first();
            $pairUnit = getQueueUnitId($UnitMatch->unit, $request->get('pairUnit'), true);

            $pairUnitId = getQueueUnitId($UnitMatch->unit, $request->get('pairUnit'), false, 'id');
            $unitId = getQueueUnitId($UnitMatch->unit, $request->get('selfUnit'), false, 'id');

            $this->updateLatestEquity($unitId, $request->get('latestEquity'));

            $pairAccount = TradeReport::with('tradingAccountCredential')
                ->where('account_id', auth()->user()->account_id)
                ->where('id', $pairUnitId)
                ->first();

            if ($pairAccount->status === 'trading') {

                UnitsEvent::dispatch(getUnitAuthId(), [
                    'pairQueueId' => $request->get('pairQueueId'),
                    'pairUnit' => $pairUnit,
                    'machine' => $request->get('pairUnitMachine'),
                    'queue_id' => $request->get('queue_id'),
                    'selfUnit' => $request->get('pairUnit')
                ], 'close-position', $request->get('pairUnitMachine'), $request->get('pairUnit'));
            }

            // Attempt to close trading position in "Ongoing Trades"
            // tab when both trading accounts are closed.
            $this->closeTradingPositionQueue($pairUnitId, $unitId, $request->get('pairQueueId'), $UnitMatch);

            return response()->json(1);
        }

        return response()->json(0);
    }

    private function recordTradeHistory($tradeAccountId, $startingDailyEquity, $latestEquity)
    {
        $tradeHistory = new TradeHistoryModel();
        $tradeHistory->account_id = auth()->user()->account_id;
        $tradeHistory->trade_account_credential_id = $tradeAccountId;
        $tradeHistory->starting_daily_equity = (float) $startingDailyEquity;
        $tradeHistory->latest_equity = (float) $latestEquity;
        $tradeHistory->save();

        return response()->json(1);
    }

    private function closeTradingPositionQueue($id1, $id2, $pairQueueId, $UnitMatch)
    {
        $item1 = TradeReport::where('id', $id1)->first();
        $item2 = TradeReport::where('id', $id2)->first();

        if ($item1->status !== 'trading'&& $item2->status !== 'trading') {
            // close position
            $trade = PairedItems::where('account_id', auth()->user()->account_id)
                ->where('id', $pairQueueId)
                ->first();

            $trade->delete();
            $UnitMatch->delete();

            $this->recordTradeHistory($item1->trade_account_credential_id, $item1->starting_daily_equity, $item1->latest_equity);
            $this->recordTradeHistory($item2->trade_account_credential_id, $item2->starting_daily_equity, $item2->latest_equity);

            UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-closed');
        }

        return false;
    }

    private function updateLatestEquity($unitId, $latestEquity)
    {
        $tradeAccount = TradeReport::with('tradingAccountCredential')
            ->where('account_id', auth()->user()->account_id)
            ->where('id', $unitId)
            ->first();

        if ($tradeAccount->status !== 'trading') {
            return false;
        }

        $latestEquity = (float) str_replace(' ', '', $latestEquity);
        $tradeAccount->latest_equity = $latestEquity;
        $tradeAccount->status = $this->getStatusByLatestEquity($tradeAccount, $latestEquity);
        $tradeAccount->update();

        return true;
    }

    private function getStatusByLatestEquity($tradeAccount, $latestEquity)
    {
        $tradeAccount = $tradeAccount->toArray();
        $maxDrawdownAllowance = 50;

        $latestEquity = (float) $latestEquity;
        $startingBalance = (float) $tradeAccount['trading_account_credential']['starting_balance'];
        $currentPhase = str_replace('-', '_', $tradeAccount['trading_account_credential']['current_phase']);
        $maxDrawdown = (float) $tradeAccount['trading_account_credential'][$currentPhase .'_max_drawdown'] + $maxDrawdownAllowance;
        $dailyDrawdown = (float) $tradeAccount['trading_account_credential'][$currentPhase .'_daily_drawdown'];
        $dailyTp = (float) $tradeAccount['trading_account_credential'][$currentPhase .'_daily_target_profit'];
        $startingDailyEquity = (float) $tradeAccount['starting_daily_equity'];

        $totalAsset = $latestEquity - $startingBalance;
        $pnl = $latestEquity - $startingDailyEquity;

        if ($totalAsset <= -$maxDrawdown) {
            return 'breached';
        }

        if (!empty($dailyDrawdown)) {
            if ($pnl >= ($dailyTp / 2) || $pnl <= -($dailyDrawdown/2)) {
                return 'abstained';
            }
        } else {
            if ($pnl >= ($dailyTp / 2)) {
                return 'abstained';
            }
        }

        return 'idle';
    }

    public function unitReady(Request $request)
    {
        $UnitMatch = TradingUnitQueueModel::where('account_id', auth()->user()->account_id)
            ->where('queue_id', $request->get('queue_id'))->first();

        if ($UnitMatch) {

            $currentUtcTime = Carbon::now('UTC');
            $futureTime = $currentUtcTime->addSeconds(10); // seconds of delay allowance before trade button click.
            $unitMatchId = getQueueUnitIdMachine($UnitMatch->unit);

            $args = [
                'pairQueueId' => $request->get('pairQueueId'),
                'year' => $futureTime->format('Y'),
                'month' => $futureTime->format('m'),
                'day' => $futureTime->format('d'),
                'hours' => $futureTime->format('H'),
                'minutes' => $futureTime->format('i'),
                'seconds' => $futureTime->format('s'),
                'purchase_type' => $UnitMatch->purchase_type,
                'pairUnit' => $request->get('unit'),
                'selfUnit' => $unitMatchId,
                'queue_id' => $request->get('queue_id'),
                'machine' => $UnitMatch->machine,
                'pairUnitMachine' => $request->get('machine')
            ];

            UnitsEvent::dispatch(getUnitAuthId(), $args, 'do-trade', $UnitMatch->machine, $unitMatchId);

            $args['purchase_type'] = ($UnitMatch->purchase_type === 'sell')? 'buy' : 'sell';
            $args['machine'] = $request->get('machine');
            $args['pairUnitMachine'] = $UnitMatch->machine;
            $args['pairUnit'] = $unitMatchId;
            $args['selfUnit'] = $request->get('unit');

            UnitsEvent::dispatch(getUnitAuthId(), $args, 'do-trade', $request->get('machine'), $request->get('unit'));

            $UnitMatch->unit = $UnitMatch->unit .','. $request->get('itemId') .'|'. $request->get('unit');
            $UnitMatch->update();
        } else {
            $newQueue = new TradingUnitQueueModel();
            $newQueue->account_id = auth()->user()->account_id;
            $newQueue->unit = $request->get('itemId') .'|'. $request->get('unit');
            $newQueue->machine = $request->get('machine');
            $newQueue->queue_id = $request->get('queue_id');
            $newQueue->purchase_type = $request->get('purchase_type');
            $newQueue->save();
        }
    }

    public function initiateTrade(Request $request)
    {
        $queueId = $this->generateQueueId();
        $data = $request->except('_token');
        $pairId = $data['paired_id'];

        unset($data['paired_id']);

        foreach ($data as $unitTradeItem) {
            $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $unitTradeItem['unit']);

            if (!$isUnitConnected) {
                return response()->json([
                    'error' => 'Unit '. $unitTradeItem['unit'] .' is not connected.'
                ]);
            }
        }

        foreach ($data as $key => $item) {

            $unitItem = TradeReport::with('funder', 'tradingAccountCredential.userAccount.funderAccountCredential')
                ->where('id', $item['id'])
                ->where('account_id', auth()->user()->account_id)
                ->first();

            $credential = getFunderAccountCredential($unitItem);

            $purchase_type = $item['purchase_type'];

            if ($purchase_type === 'buy-cross-phase') {
                $purchase_type = 'buy';
            }

            if ($purchase_type === 'sell-cross-phase') {
                $purchase_type = 'sell';
            }

            UnitsEvent::dispatch(getUnitAuthId(), [
                'pairQueueId' => $pairId,
                'account_id' => $item['account_id'],
                'latest_equity' => $item['latest_equity'],
                'purchase_type' => $purchase_type,
                'symbol' => $item['symbol'],
                'order_amount' => $item['order_amount'],
                'take_profit_ticks' => $item['take_profit_ticks'],
                'stop_loss_ticks' => $item['stop_loss_ticks'],
                'queue_id' => $queueId,
                'machine' => $item['machine'],
                'unit' => $item['unit'],
                'itemId' => $item['id'],
                'loginUsername' => $credential['loginUsername'],
                'loginPassword' => $credential['loginPassword']
            ], 'initiate-trade', $item['machine'], $item['unit']);

            $unitItem->purchase_type = $purchase_type;
            $unitItem->order_amount = $item['order_amount'];
            $unitItem->take_profit_ticks = $item['take_profit_ticks'];
            $unitItem->stop_loss_ticks = $item['stop_loss_ticks'];
            $unitItem->status = 'trading';
            $unitItem->update();

        }

        $pairedItems = PairedItems::where('id', $pairId)
            ->where('account_id', auth()->user()->account_id)->first();

        $pairedItems->status = 'trading';
        $pairedItems->update();


//        $unit1 = TradeReport::where('id', $data['unit1']['id'])->where('account_id', auth()->user()->account_id)->first();
//        $unit1->purchase_type = $data['unit1']['purchase_type'];
//        $unit1->order_amount = $data['unit1']['order_amount'];
//        $unit1->take_profit_ticks = $data['unit1']['take_profit_ticks'];
//        $unit1->stop_loss_ticks = $data['unit1']['stop_loss_ticks'];
//        $unit1->status = 'trading';
//        $unit1->update();
//
//        $unit2 = TradeReport::where('id', $data['unit2']['id'])->where('account_id', auth()->user()->account_id)->first();
//        $unit2->purchase_type = $data['unit2']['purchase_type'];
//        $unit2->order_amount = $data['unit2']['order_amount'];
//        $unit2->take_profit_ticks = $data['unit2']['take_profit_ticks'];
//        $unit2->stop_loss_ticks = $data['unit2']['stop_loss_ticks'];
//        $unit2->status = 'trading';
//        $unit2->update();

        return response()->json(['message' => __('Initiating unit trade.')]);
    }

    private function generateQueueId()
    {
        $uuid = (string) Str::uuid();
        $currentDateTime = Carbon::now()->format('Ymd_His');

        return $uuid . '_' . $currentDateTime;
    }
}
