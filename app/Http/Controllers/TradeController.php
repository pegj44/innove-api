<?php

namespace App\Http\Controllers;

use App\Events\UnitResponse;
use App\Events\UnitsEvent;
use App\Models\PairedItems;
use App\Models\TradeHistoryModel;
use App\Models\TradeHistoryV2Model;
use App\Models\TradeHistoryV3Model;
use App\Models\TradeQueueModel;
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

    public function tradeErrorReport(Request $request)
    {
        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $request->get('queue_db_id'))
            ->first();

        $queueItem->status = 'pairing-error';
        $queueItem->update();

        UnitResponse::dispatch(auth()->user()->account_id, [
            'queue_db_id' => $request->get('queue_db_id'),
            'itemId' => $request->get('itemId'),
            'message' => __('Failed to initialize')
        ], 'trade-initialize-error');

        return response()->json($queueItem);
    }

    public function closePosition(Request $request)
    {
        $dataRaw = $request->get('data');
        $dataArr = explode('|', $dataRaw);
        $dataFormatted = [];

        foreach ($dataArr as $item) {
            $subData = explode(':', $item);
            $dataFormatted[$subData[0]] = $subData[1];
        }
info(print_r([
    'closePositionTest' => $dataFormatted
], true));
        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $dataFormatted['queue_id'])
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => __('Pair queue not found.')]);
        }

        $queueData = maybe_unserialize($queueItem->data);
        $itemData = $queueData[$dataFormatted['itemId']];

        unset($queueData[$dataFormatted['itemId']]);

        $matchPairId = array_keys($queueData);
        $matchPairData = $queueData[$matchPairId[0]];

        $tradeAccount = TradeReport::with('tradingAccountCredential')
            ->where('account_id', auth()->user()->account_id)
            ->where('id', $dataFormatted['itemId'])
            ->first();

        $this->updateLatestEquity($tradeAccount, $dataFormatted['latestEquity']);
        $this->recordTradeHistory($tradeAccount, $dataFormatted['latestEquity']);

        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $matchPairData['unit_id']);

        if (!$isUnitConnected) {
            return response()->json(['error' => __('Unit '. $matchPairData['unit_id'] .' is not connected')]);
        }

        $pairAccount = TradeReport::with('tradingAccountCredential')
            ->where('account_id', auth()->user()->account_id)
            ->where('id', $matchPairId[0])
            ->first();

        if ($pairAccount->status === 'trading') {
            UnitsEvent::dispatch(getUnitAuthId(), [
                'queue_id' => $queueItem->id,
                'itemId' => $matchPairId[0],
            ], 'close-position', $matchPairData['platform_type'], $matchPairData['unit_id']);
        }

        // Attempt to close trading position in "Ongoing Trades"
        // tab when both trading accounts are closed.
        $this->closeTradingPositionQueue($tradeAccount, $pairAccount, $queueItem);

//        info(print_r([
//            'closePosition2' => [
//                $dataFormatted['itemId'],
//                $matchPairId[0],
//            ]
//        ], true));

        return response()->json($dataFormatted);
    }

//    public function closePosition_old(Request $request)
//    {
//        $dataRaw = $request->get('data');
//        $dataArr = explode('|', $dataRaw);
//        $dataFormatted = [];
//
//        foreach ($dataArr as $item) {
//            $subData = explode(':', $item);
//            $dataFormatted[$subData[0]] = $subData[1];
//        }
//
//        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $dataFormatted['pairUnit']);
//
//        if ($isUnitConnected) {
//            $UnitMatch = TradingUnitQueueModel::where('account_id', auth()->user()->account_id)
//                ->where('queue_id', $dataFormatted['queue_id'])->first();
//            $pairUnit = getQueueUnitId($UnitMatch->unit, $dataFormatted['pairUnit'], true);
//
//            $pairUnitId = getQueueUnitId($UnitMatch->unit, $dataFormatted['pairUnit'], false, 'id');
//            $unitId = getQueueUnitId($UnitMatch->unit, $dataFormatted['selfUnit'], false, 'id');
//
//            $tradeAccount = TradeReport::with('tradingAccountCredential', 'tradingAccountCredential.historyV3')
//                ->where('account_id', auth()->user()->account_id)
//                ->where('id', $unitId)
//                ->first();
//
//            $this->updateLatestEquity($tradeAccount, $dataFormatted['latestEquity']);
//            $this->recordTradeHistory($tradeAccount, $dataFormatted['latestEquity']);
//
//            $pairAccount = TradeReport::with('tradingAccountCredential')
//                ->where('account_id', auth()->user()->account_id)
//                ->where('id', $pairUnitId)
//                ->first();
//
//            if ($pairAccount->status === 'trading') {
//
//                UnitsEvent::dispatch(getUnitAuthId(), [
//                    'pairQueueId' => $dataFormatted['pairQueueId'],
//                    'pairUnit' => $pairUnit,
//                    'machine' => $dataFormatted['pairUnitMachine'],
//                    'queue_id' => $dataFormatted['queue_id'],
//                    'selfUnit' => $dataFormatted['pairUnit']
//                ], 'close-position', $dataFormatted['pairUnitMachine'], $dataFormatted['pairUnit']);
//            }
//
//            // Attempt to close trading position in "Ongoing Trades"
//            // tab when both trading accounts are closed.
//            $this->closeTradingPositionQueue($pairUnitId, $unitId, $dataFormatted['pairQueueId'], $UnitMatch);
//
//            return response()->json(1);
//        }
//
//        return response()->json(0);
//    }

    private function recordTradeHistory($tradeAccount, $latestEquity)
    {
        $latestEquity = (float) $latestEquity;
        $tradeHistory = TradeHistoryV3Model::where('trade_account_credential_id', $tradeAccount->trade_account_credential_id)
            ->latest('created_at')
            ->first();

        if (empty($tradeHistory)) {
            $this->createTradeHistory($tradeAccount, $latestEquity);
        } else {

            $isToday = Carbon::parse($tradeHistory->created_at)->isToday();
            $highestBalance = ($latestEquity > $tradeHistory->highest_balance)? $latestEquity : $tradeHistory->highest_balance;

            if ($isToday) {
                $tradeHistory->latest_equity = $latestEquity;
                $tradeHistory->highest_balance = $highestBalance;
                $tradeHistory->update();
            } else {
                $this->createTradeHistory($tradeAccount, $latestEquity, $highestBalance);
            }
        }

        return response()->json(1);
    }

    private function createTradeHistory($tradeAccount, $latestEquity, $highestBalance = null)
    {
        $item = new TradeHistoryV3Model();
        $item->trade_account_credential_id = $tradeAccount->trade_account_credential_id;
        $item->starting_daily_equity = (float) $tradeAccount->starting_daily_equity;
        $item->latest_equity = $latestEquity;
        $item->status = $tradeAccount->tradingAccountCredential->current_phase;
        $item->highest_balance = (!empty($highestBalance))? $highestBalance : $latestEquity;
        $item->save();
    }

    private function closeTradingPositionQueue($item1, $item2, $queueItem)
    {
        if ($item1->status !== 'trading' && $item2->status !== 'trading') {
            $queueItem->delete();
            UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-closed');
        }

        return false;
    }

    private function closeTradingPositionQueue_old($id1, $id2, $pairQueueId, $UnitMatch)
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

//            $this->recordTradeHistory($item1->trade_account_credential_id, $item1->starting_daily_equity, $item1->latest_equity);
//            $this->recordTradeHistory($item2->trade_account_credential_id, $item2->starting_daily_equity, $item2->latest_equity);

            UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-closed');
        }

        return false;
    }

    private function updateLatestEquity($tradeAccount, $latestEquity)
    {
        if ($tradeAccount->status !== 'trading') {
            return false;
        }

        $latestEquity = (float) str_replace(' ', '', $latestEquity);
        $tradeAccount->latest_equity = $latestEquity;
        $tradeAccount->status = $this->getStatusByLatestEquity($tradeAccount, $latestEquity);
        $tradeAccount->update();

        return true;
    }

    public function getCalculatedRdd($tradeAccount)
    {
        $currentPhase = str_replace('phase-', '', $tradeAccount['trading_account_credential']['current_phase']);
        $highestBalArr = [];

        $startingBal = (float) $tradeAccount['trading_account_credential']['starting_balance'];
        $latestEqty = (float) $tradeAccount['latest_equity'];
        $maxDrawdown = (float) $tradeAccount['trading_account_credential']['phase_'. $currentPhase .'_max_drawdown'];

        if ($tradeAccount['trading_account_credential']['drawdown_type'] === 'trailing_endofday') {
            if (!empty($tradeAccount['trading_account_credential']['history_v3'])) {
                foreach ($tradeAccount['trading_account_credential']['history_v3'] as $tradeItem) {
                    $highestBalArr[] = (float) $tradeItem['highest_balance'];
                }
            }

            $highestBal = (!empty($highestBalArr))? max($highestBalArr) : $latestEqty;
            $bufferZone = $startingBal + $maxDrawdown;

            if ($highestBal >= $bufferZone) {
                return $latestEqty - $startingBal;
            }

            if ($highestBal <= $startingBal) {
                $maxThreshold = $startingBal - $maxDrawdown;
            } else {
                $maxThreshold = $highestBal - $maxDrawdown;
            }

            return $latestEqty - $maxThreshold;
        }

        if ($tradeAccount['trading_account_credential']['drawdown_type'] === 'static') {
            $maxTreshold = $startingBal - $maxDrawdown;
            return $latestEqty - $maxTreshold;
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
        $queueItem = TradeQueueModel::where('id', $request->get('queue_db_id'))
            ->where('account_id', auth()->user()->account_id)
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => 'Pair queue not found.']);
        }

        $readyUnits = maybe_unserialize($queueItem->unit_ready);

        if (empty($readyUnits)) {
            $readyUnits = [$request->get('itemId')];
        } else {
            $readyUnits[] = $request->get('itemId');
        }

        $readyUnits = array_unique($readyUnits);

        $queueItem->unit_ready = maybe_serialize($readyUnits);
        $queueItem->update();

        if (count($readyUnits) > 1) {
            UnitResponse::dispatch(auth()->user()->account_id, [
                'queue_db_id' => $queueItem->id
            ], 'initialize-complete');
        }

        return response()->json(1);
    }

    public function unitReady_old(Request $request)
    {
        $UnitMatch = TradingUnitQueueModel::where('account_id', auth()->user()->account_id)
            ->where('queue_id', $request->get('queue_id'))->first();

        if ($UnitMatch) {

            $currentUtcTime = Carbon::now('UTC');
            $futureTime = $currentUtcTime->addSeconds(3); // seconds of delay allowance before trade button click.
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

//            UnitsEvent::dispatch(getUnitAuthId(), $args, 'do-trade', $UnitMatch->machine, $unitMatchId);

            $args['purchase_type'] = ($UnitMatch->purchase_type === 'sell')? 'buy' : 'sell';
            $args['machine'] = $request->get('machine');
            $args['pairUnitMachine'] = $UnitMatch->machine;
            $args['pairUnit'] = $unitMatchId;
            $args['selfUnit'] = $request->get('unit');
            $args['account_id'] = $request->get('account_id');
            $args['account_id_short'] = getFunderAccountShortName($request->get('account_id'));

//            UnitsEvent::dispatch(getUnitAuthId(), $args, 'do-trade', $request->get('machine'), $request->get('unit'));

            $UnitMatch->unit = $UnitMatch->unit .','. $request->get('itemId') .'|'. $request->get('unit');
            $UnitMatch->funder_account_id = $UnitMatch->funder_account_id .'|'. $request->get('account_id');
            $UnitMatch->update();

//            UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-started');
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

    public function doTrade()
    {

    }

    public function pairUnits(Request $request)
    {
        $itemIds = $request->all();

        $items = TradeReport::with([
            'tradingAccountCredential',
            'tradingAccountCredential.funder',
            'tradingAccountCredential.userAccount.funderAccountCredential',
            'tradingAccountCredential.userAccount.tradingUnit'
        ])
        ->whereIn('id', $itemIds)
        ->get();

        if (empty($items)) {
            return response()->json([]);
        }

        if ($items->count() < 1) {
            return response()->json(['error' => 'Unable to proceed pairing. One of the paired items no longer exists.']);
        }

        $data = [];

        foreach ($items->toArray() as $item) {

            $dailyEquity = (float) $item['starting_daily_equity'];
            $latestEquity = (float) $item['latest_equity'];
            $pnl = $latestEquity - $dailyEquity;

            $credential = getFunderAccountCredential($item);

            $data[$item['id']] = [
                'id' => $item['id'],
                'funder' => $item['trading_account_credential']['funder']['alias'],
                'funder_theme' => $item['trading_account_credential']['funder']['theme'],
                'funder_account_id_long' => $item['trading_account_credential']['funder_account_id'],
                'funder_account_id_short' => getFunderAccountShortName($item['trading_account_credential']['funder_account_id']),
                'unit_name' => $item['trading_account_credential']['user_account']['trading_unit']['name'],
                'unit_id' => $item['trading_account_credential']['user_account']['trading_unit']['unit_id'],
                'starting_balance' => $item['trading_account_credential']['starting_balance'],
                'starting_equity' => $item['starting_daily_equity'],
                'latest_equity' => $item['latest_equity'],
                'pnl' => number_format($pnl, 2),
                'rdd' => round($this->getCalculatedRdd($item), 0),
                'symbol' => $item['trading_account_credential']['symbol'],
                'order_amount' => TradePairAccountsController::getCalculatedOrderAmountV2($item, $item['trading_account_credential']['asset_type']),
                'tp' => TradePairAccountsController::getTakeProfitTicks($item),
                'sl' => TradePairAccountsController::getStopLossTicks($item),
                'platform_type' => $item['trading_account_credential']['platform_type'],
                'login_username' => $credential['loginUsername'],
                'login_password' => $credential['loginPassword']
            ];
        }

        return response()->json($data);
    }

    public function startTrade(Request $request)
    {
        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $request->get('queue_id'))
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => __('Trade queue not found.')]);
        }

        $data = maybe_unserialize($queueItem->data);
        $currentUtcTime = Carbon::now('UTC');
        $futureTime = $currentUtcTime->addSeconds(3); // seconds of delay allowance before trade button click.

        foreach ($data as $unitTradeItem) {
            $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $unitTradeItem['unit_id']);

            if (!$isUnitConnected) {
                return response()->json([
                    'error' => 'Unit '. $unitTradeItem['unit_id'] .' is not connected.'
                ]);
            }
        }

        foreach ($data as $unitId => $unitItem) {

            $pairItem = TradeReport::where('account_id', auth()->user()->account_id)
                ->where('id', $unitId)
                ->first();

            $pairItem->status = 'trading';
            $pairItem->update();

            $args = [
                'year' => $futureTime->format('Y'),
                'month' => $futureTime->format('m'),
                'day' => $futureTime->format('d'),
                'hours' => $futureTime->format('H'),
                'minutes' => $futureTime->format('i'),
                'seconds' => $futureTime->format('s'),
                'purchase_type' => $unitItem['purchase_type'],
                'queue_id' => $queueItem->id,
                'itemId' => $unitId,
                'funder' => [
                    'alias' => $unitItem['funder'],
                    'theme' => $unitItem['funder_theme']
                ],
                'account_id' => $unitItem['funder_account_id_long'],
                'account_id_short' => $unitItem['funder_account_id_short']
            ];

            UnitsEvent::dispatch(getUnitAuthId(), $args, 'do-trade', $unitItem['platform_type'], $unitItem['unit_id']);
        }

        $queueItem->status = 'trading';
        $queueItem->update();

        UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-started');

        return response()->json(['message' => __('Trade Started.')]);
    }

    public function reInitializeTrade(Request $request)
    {
        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $request->get('queue_id'))
            ->first();

        if (!$queueItem) {
            return response()->json(['error', 'Queue pair not found.']);
        }

        $data = maybe_unserialize($queueItem->data);

        try {
            $this->initiateTradeV2(new Request([
                'data' => $data,
                'queueItem' => $queueItem
            ]));
            return response()->json(['message' => __('Initiating Account Pairing.')]);
        } catch (\Exception $e) {

            return response()->json(['error' => __('Error initiating the account pair.')]);
        }
    }

    public function initiateTradeV2(Request $request)
    {
        $data = $request->get('data');

        foreach ($data as $unitTradeItem) {
            $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $unitTradeItem['unit_id']);

            if (!$isUnitConnected) {
                return response()->json([
                    'error' => 'Unit '. $unitTradeItem['unit_id'] .' is not connected.'
                ]);
            }
        }

        if (empty($request->get('queueItem'))) {
            $queueId = $this->generateQueueId();

            $tradeQueue = new TradeQueueModel();
            $tradeQueue->account_id = auth()->user()->account_id;
            $tradeQueue->queue_id = $queueId;
            $tradeQueue->data = maybe_serialize($data);
            $tradeQueue->status = 'pairing';
            $tradeQueue->save();

        } else {
            $tradeQueue = $request->get('queueItem');
            $tradeQueue->status = 'pairing';
            $tradeQueue->update();

            $queueId = $tradeQueue->queue_id;
        }

        foreach ($data as $itemId => $item) {

            $pairUnit = TradeReport::where('id', $itemId)->first();
            $pairUnit->status = 'pairing';
            $pairUnit->update();

            UnitsEvent::dispatch(getUnitAuthId(), [
                'account_id' => $item['funder_account_id_long'],
                'account_id_short' => $item['funder_account_id_short'],
                'purchase_type' => $item['purchase_type'],
                'symbol' => $item['symbol'],
                'order_amount' => $item['order_amount'],
                'take_profit_ticks' => $item['tp'],
                'stop_loss_ticks' => $item['sl'],
                'queue_id' => $queueId,
                'queue_db_id' => $tradeQueue->id,
                'itemId' => $itemId,
                'loginUsername' => $item['login_username'],
                'loginPassword' => $item['login_password'],
                'funder' => [
                    'alias' => $item['funder'],
                    'theme' => $item['funder_theme']
                ],
                'unit_name' => $item['unit_name'],
                'starting_balance' => $item['starting_balance'],
                'starting_equity' => $item['starting_equity'],
                'latest_equity' => $item['latest_equity'],
                'rdd' => $item['rdd']
            ], 'initiate-trade', $item['platform_type'], $item['unit_id']);
        }

        return response()->json(['message' => __('Account pairing initiated.')]);
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

//            UnitsEvent::dispatch(getUnitAuthId(), [
//                'pairQueueId' => $pairId,
//                'account_id' => $item['account_id'],
//                'account_id_short' => getFunderAccountShortName($item['account_id']),
//                'latest_equity' => $item['latest_equity'],
//                'purchase_type' => $purchase_type,
//                'symbol' => $item['symbol'],
//                'order_amount' => $item['order_amount'],
//                'take_profit_ticks' => $item['take_profit_ticks'],
//                'stop_loss_ticks' => $item['stop_loss_ticks'],
//                'queue_id' => $queueId,
//                'machine' => $item['machine'],
//                'unit' => $item['unit'],
//                'itemId' => $item['id'],
//                'loginUsername' => $credential['loginUsername'],
//                'loginPassword' => $credential['loginPassword'],
//                'funder' => [
//                    'alias' => $unitItem->tradingAccountCredential->funder->alias,
//                    'theme' => $unitItem->tradingAccountCredential->funder->theme
//                ]
//            ], 'initiate-trade', $item['machine'], $item['unit']);

            $unitItem->purchase_type = $item['purchase_type'];
            $unitItem->order_amount = $item['order_amount'];
            $unitItem->take_profit_ticks = $item['take_profit_ticks'];
            $unitItem->stop_loss_ticks = $item['stop_loss_ticks'];
            $unitItem->status = 'pairing';
            $unitItem->update();

        }

        $pairedItems = PairedItems::where('id', $pairId)
            ->where('account_id', auth()->user()->account_id)->first();

        $pairedItems->status = 'pairing';
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
