<?php

namespace App\Http\Controllers;

use App\Events\UnitResponse;
use App\Events\UnitsEvent;
use App\Models\PairedItems;
use App\Models\TradeHistoryModel;
use App\Models\TradeHistoryV2Model;
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
        $dataRaw = $request->get('data');
        $dataArr = explode('|', $dataRaw);
        $dataFormatted = [];

        foreach ($dataArr as $item) {
            $subData = explode(':', $item);
            $dataFormatted[$subData[0]] = $subData[1];
        }

        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $dataFormatted['pairUnit']);

        if ($isUnitConnected) {
            $UnitMatch = TradingUnitQueueModel::where('account_id', auth()->user()->account_id)
                ->where('queue_id', $dataFormatted['queue_id'])->first();
            $pairUnit = getQueueUnitId($UnitMatch->unit, $dataFormatted['pairUnit'], true);

            $pairUnitId = getQueueUnitId($UnitMatch->unit, $dataFormatted['pairUnit'], false, 'id');
            $unitId = getQueueUnitId($UnitMatch->unit, $dataFormatted['selfUnit'], false, 'id');

            $this->updateLatestEquity($unitId, $dataFormatted['latestEquity']);

            $pairAccount = TradeReport::with('tradingAccountCredential')
                ->where('account_id', auth()->user()->account_id)
                ->where('id', $pairUnitId)
                ->first();

            if ($pairAccount->status === 'trading') {

                UnitsEvent::dispatch(getUnitAuthId(), [
                    'pairQueueId' => $dataFormatted['pairQueueId'],
                    'pairUnit' => $pairUnit,
                    'machine' => $dataFormatted['pairUnitMachine'],
                    'queue_id' => $dataFormatted['queue_id'],
                    'selfUnit' => $dataFormatted['pairUnit']
                ], 'close-position', $dataFormatted['pairUnitMachine'], $dataFormatted['pairUnit']);
            }

            // Attempt to close trading position in "Ongoing Trades"
            // tab when both trading accounts are closed.
            $this->closeTradingPositionQueue($pairUnitId, $unitId, $dataFormatted['pairQueueId'], $UnitMatch);

            return response()->json(1);
        }

        return response()->json(0);
    }

    private function recordTradeHistory($tradeAccountId, $startingDailyEquity, $latestEquity)
    {
        $item = new TradeHistoryV2Model();
        $item->trade_account_credential_id = $tradeAccountId;
        $item->starting_daily_equity = (float) $startingDailyEquity;
        $item->latest_equity = (float) $latestEquity;
        $item->save();

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

    public function getCalculatedRdd($data)
    {
        $currentPhase = str_replace('phase-', '', $data['trading_account_credential']['current_phase']);

        if ($data['trading_account_credential']['drawdown_type'] === 'trailing_endofday') {
            $highestBal = [$data['trading_account_credential']['starting_balance']];

            if (!empty($data['trading_account_credential']['history_v2'])) {
                foreach ($data['trading_account_credential']['history_v2'] as $tradeItem) {
                    $highestBal[] = (float) $tradeItem['latest_equity'];
                }
            }

            $highestBal = max($highestBal);
            $maxTreshold = (float) $highestBal - (float) $data['trading_account_credential']['phase_'. $currentPhase .'_max_drawdown'];

            return ($highestBal >= $data['latest_equity'])? (float) $data['latest_equity'] - $maxTreshold : $data['trading_account_credential']['phase_'. $currentPhase .'_max_drawdown'];
        }

        if ($data['trading_account_credential']['drawdown_type'] === 'static') {
            $latestEquity = (float) $data['latest_equity'];
            $maxTreshold = (float) $data['trading_account_credential']['starting_balance'] - (float) $data['trading_account_credential']['phase_'. $currentPhase .'_max_drawdown'];

            return $latestEquity - $maxTreshold;
        }

        return null;
    }

    private function getStatusByLatestEquity($tradeAccount, $latestEquity)
    {
        $tradeAccount = $tradeAccount->toArray();
        $maxDrawdownAllowance = 50;

        $latestEquity = (float) $latestEquity;
//        $startingBalance = (float) $tradeAccount['trading_account_credential']['starting_balance'];
        $currentPhase = str_replace('-', '_', $tradeAccount['trading_account_credential']['current_phase']);
        $maxDrawdown = (float) $tradeAccount['trading_account_credential'][$currentPhase .'_max_drawdown'] + $maxDrawdownAllowance;
        $dailyDrawdown = (float) $tradeAccount['trading_account_credential'][$currentPhase .'_daily_drawdown'];
        $dailyTp = (float) $tradeAccount['trading_account_credential'][$currentPhase .'_daily_target_profit'];
        $startingDailyEquity = (float) $tradeAccount['starting_daily_equity'];

//        $totalAsset = $latestEquity - $startingBalance;
        $pnl = $latestEquity - $startingDailyEquity;

        $rdd = $this->getCalculatedRdd($tradeAccount);

        info(print_r([
            'getStatusByLatestEquity' => [
                'funder' => (!empty($tradeAccount['trading_account_credential']['funder_account_id']))? $tradeAccount['trading_account_credential']['funder_account_id'] : 'no funder',
                'rdd' => $rdd
            ]
        ], true));

        if ($rdd !== null && $rdd < 100) {
            return 'breached';
        }

        if (!empty($dailyDrawdown)) {
//            if ($pnl >= ($dailyTp / 2) || $pnl <= -($dailyDrawdown/2)) {
//                return 'abstained';
//            }

            $positiveThreshold = $dailyDrawdown * 0.9;
            $negativeThreshold = -$dailyDrawdown * 0.9;

            info(print_r([
                'tradeReport' => [
                    'funder' => (!empty($tradeAccount['trading_account_credential']['funder_account_id']))? $tradeAccount['trading_account_credential']['funder_account_id'] : 'no funder',
                    '$dailyDrawdown' => $dailyDrawdown,
                    '$positiveThreshold' => $positiveThreshold,
                    '$negativeThreshold' => $negativeThreshold,
                    '$pnl' => $pnl,
                    'isAbstained' => ($pnl >= $positiveThreshold || $pnl <= $negativeThreshold)
                ]
            ], true));

            if ($pnl >= $positiveThreshold || $pnl <= $negativeThreshold) {
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
                'account_id' => $UnitMatch->funder_account_id,
                'account_id_short' => getFunderAccountShortName($UnitMatch->funder_account_id),
                'pairUnitMachine' => $request->get('machine')
            ];

            UnitsEvent::dispatch(getUnitAuthId(), $args, 'do-trade', $UnitMatch->machine, $unitMatchId);

            $args['purchase_type'] = ($UnitMatch->purchase_type === 'sell')? 'buy' : 'sell';
            $args['machine'] = $request->get('machine');
            $args['pairUnitMachine'] = $UnitMatch->machine;
            $args['pairUnit'] = $unitMatchId;
            $args['selfUnit'] = $request->get('unit');
            $args['account_id'] = $request->get('account_id');
            $args['account_id_short'] = getFunderAccountShortName($request->get('account_id'));

            UnitsEvent::dispatch(getUnitAuthId(), $args, 'do-trade', $request->get('machine'), $request->get('unit'));

            $UnitMatch->unit = $UnitMatch->unit .','. $request->get('itemId') .'|'. $request->get('unit');
            $UnitMatch->funder_account_id = $UnitMatch->funder_account_id .'|'. $request->get('account_id');
            $UnitMatch->update();

            UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-started');
        } else {
            $newQueue = new TradingUnitQueueModel();
            $newQueue->account_id = auth()->user()->account_id;
            $newQueue->unit = $request->get('itemId') .'|'. $request->get('unit');
            $newQueue->machine = $request->get('machine');
            $newQueue->queue_id = $request->get('queue_id');
            $newQueue->purchase_type = $request->get('purchase_type');
            $newQueue->funder_account_id = $request->get('account_id'); // the funder account id: ex: FTT-RALLY-0000
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
                'account_id_short' => getFunderAccountShortName($item['account_id']),
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
                'loginPassword' => $credential['loginPassword'],
                'funder' => [
                    'alias' => $unitItem->tradingAccountCredential->funder->alias,
                    'theme' => $unitItem->tradingAccountCredential->funder->theme
                ]
            ], 'initiate-trade', $item['machine'], $item['unit']);

            $unitItem->purchase_type = $item['purchase_type'];
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

        return response()->json(['message' => __('Initiating unit trade.')]);
    }

    private function generateQueueId()
    {
        $uuid = (string) Str::uuid();
        $currentDateTime = Carbon::now()->format('Ymd_His');

        return $uuid . '_' . $currentDateTime;
    }

    public function setupTradoverse(Request $request)
    {
        UnitsEvent::dispatch(getUnitAuthId(), [

        ], 'setup-tradoverse', 'SetupTradoverse', $request->get('unitId'));
    }
}
