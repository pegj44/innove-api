<?php

namespace App\Http\Controllers;

use App\Events\UnitResponse;
use App\Events\UnitsEvent;
use App\Events\WebPush;
use App\Models\Funder;
use App\Models\PairedItems;
use App\Models\TradeHistoryModel;
use App\Models\TradeHistoryV2Model;
use App\Models\TradeHistoryV3Model;
use App\Models\TradeQueueModel;
use App\Models\TradeReport;
use App\Models\TradingIndividual;
use App\Models\TradingUnitQueueModel;
use App\Models\UnitProcessesModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TradeController extends Controller
{
    public $stopLossPips = 5.1;
    public $takeProfitPips = 4.9;
    public $mandatoryStopLossPercentage = 0.02;
    public $volumeMultiplierPercentage = 0.8;

    public function tradeRecover(Request $request)
    {
        $tradeQueueId = $request->get('queue_id');

        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $tradeQueueId)
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => 'Trade queue does not exists.']);
        }

        $queueData = maybe_unserialize($queueItem->data);

        foreach ($queueData as $itemId => $item) {
            UnitsEvent::dispatch(getUnitAuthId(), [
                'account_id' => $item['funder_account_id_long'],
                'account_id_short' => $item['funder_account_id_short'],
                'purchase_type' => $item['purchase_type'],
                'symbol' => $item['symbol'],
                'order_amount' => $item['order_amount'],
                'take_profit_ticks' => $item['tp'],
                'stop_loss_ticks' => $item['sl'],
                'queue_id' => (int) $tradeQueueId,
                'queue_db_id' => $tradeQueueId,
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
            ], 'recover-trade', $item['platform_type'], $item['unit_id']);
        }

        return response()->json($queueData);
    }

    public function checkAccountBreached(Request $request)
    {
        $itemId = $request->get('itemId');
        $tradeItem = TradeReport::where('id', $itemId)->first();


    }

    public function tradeErrorReport(Request $request)
    {
        $itemId = $request->get('itemId');
        $tradeQueueId = $request->get('queue_db_id');
        $errorMessage = str_replace('UiPath', 'Robot', $request->get('error'));

        info(print_r([
            'tradeErrorReport' => [
                'itemId' => $itemId,
                'error' => $request->get('error')
            ]
        ], true));

        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $tradeQueueId)
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => 'Trade queue does not exists.']);
        }

        $errors = maybe_unserialize($queueItem->errors);
        $errors = (!empty($errors))? $errors : [];

        $pairStatus = $request->get('pair_status');
        $errors[$itemId] = (!empty($pairStatus))? $pairStatus : $errorMessage;

        $queueItem->errors = maybe_serialize($errors);
        $queueItem->status = 'error';
        $queueItem->pair_status = $request->get('pair_status');

        $queueItem->update();

        $message = $request->get('message');
        $errorCode = $request->get('err_code');
        $action = ($errorCode === 'initialize_error')? 'trade-initialize-error' : $errorCode;

        UnitResponse::dispatch(auth()->user()->account_id, [
            'queue_db_id' => $tradeQueueId,
            'itemId' => $itemId,
            'message' => (!empty($message))? $message : __('Failed to initialize'),
            'sound' => true,
        ], $action);

        return response()->json($queueItem);
    }

    /**
     * Stop trade action
     * @param Request $request
     */
    public function stopTrade(Request $request)
    {
        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $request->get('queue_id'))
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => __('Pair queue not found.')]);
        }

        $queueData = maybe_unserialize($queueItem->data);

        unset($queueData[$request->get('itemId')]);

        $matchPairId = array_keys($queueData);
        $matchPairData = $queueData[$matchPairId[0]];

        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $matchPairData['unit_id']);

        if (!$isUnitConnected) {
            return response()->json(['error' => __('Unit '. $matchPairData['unit_name'] .' is not connected')]);
        }

        $currentDateTime = Carbon::now('Asia/Manila');

        UnitsEvent::dispatch(getUnitAuthId(), [
            'queue_id' => $queueItem->id,
            'itemId' => $matchPairId[0],
            'dateTime' => $currentDateTime->format('F j, Y g:i A'),
            'account_id' => $matchPairData['funder_account_id_long']
        ], 'stop-trade', $matchPairData['platform_type'], $matchPairData['unit_id']);
    }

    public function updateQueueReport(string $queueId, string $itemId)
    {
        $queueItem = TradeQueueModel::where('id', $queueId)
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => __('Pair queue not found.')]);
        }

        $queueData = maybe_unserialize($queueItem->data);
        $tradeItem = TradeReport::where('id', $itemId)->first();

        if (!empty($queueData[$itemId]['new_equity']) && $queueData[$itemId]['latest_equity'] != $queueData[$itemId]['new_equity']) {
            return response()->json(['message' => __('Trade history is already updated.')]);
        }

        if ($queueData[$itemId]['latest_equity'] == $tradeItem->latest_equity) {
            return response()->json(['message' => __('Trade item equity is not yet updated.')]);
        }

        $queueData[$itemId]['new_equity'] = $tradeItem->latest_equity;

        $queueItem->data = maybe_serialize($queueData);
        $queueItem->update();

        return response()->json(['message' => __('Successfully updated history item.')]);
    }

    public function reportTradeStarted(Request $request)
    {
        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $request->get('queue_id'))
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => __('Pair queue not found.')]);
        }

        $unitsTrading = [];

        if (!empty($queueItem->units_trading)) {
            $unitsTrading = maybe_unserialize($queueItem->units_trading);
        }

        $currentDateTime = Carbon::now('Asia/Manila');
//        $dateTime = $currentDateTime->format('F j, Y g:i:s A');
        $dateTime = $currentDateTime->timestamp;
        $unitsTrading[$request->get('itemId')] = $dateTime;

        $queueItem->units_trading = maybe_serialize($unitsTrading);
        $queueItem->update();

        WebPush::dispatch(auth()->user()->account_id, [
            'queueId' => $request->get('queue_id'),
            'unitId' => $request->get('itemId'),
            'dateTime' => $dateTime
        ], 'unit-trade-started');

        return response()->json($unitsTrading);
    }

    /**
     * Let the pair know the trade is closed.
     * @param Request $request
     */
    public function closeTrade(Request $request)
    {
        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $request->get('queue_id'))
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => __('Pair queue not found.')]);
        }

        $queueData = maybe_unserialize($queueItem->data);

        unset($queueData[$request->get('itemId')]);

        $matchPairId = array_keys($queueData);
        $closedItems = (!empty($queueItem->closed_items))? maybe_unserialize($queueItem->closed_items) : [];

        $currentDateTime = Carbon::now('Asia/Manila');
        $dateTime = $currentDateTime->timestamp;

        $closedItems[$request->get('itemId')] = $dateTime;

        $queueItem->closed_items = maybe_serialize($closedItems); // save closed item.
        $queueItem->update();

        WebPush::dispatch(auth()->user()->account_id, [
            'queueId' => $request->get('queue_id'),
            'unitId' => $request->get('itemId'),
            'dateTime' => $dateTime
        ], 'unit-trade-closed');

        if (isset($closedItems[$matchPairId[0]])) {
            return false;
        }
//        if (isset($matchPairId[0], $closedItems)) {
//            return false; // Pair already closed, not need to report.
//        }

        $matchPairData = $queueData[$matchPairId[0]];

        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $matchPairData['unit_id']);

        if (!$isUnitConnected) {
            return response()->json(['error' => __('Unit '. $matchPairData['unit_name'] .' is not connected')]);
        }

        $currentDateTime = Carbon::now('Asia/Manila');

//        info(print_r([
//            'closetrade' => [
//                'queue_id' => $queueItem->id,
//                'itemId' => $matchPairId[0],
//                'dateTime' => $currentDateTime->format('F j, Y g:i A'),
//                'account_id' => $matchPairData['funder_account_id_long']
//            ]
//        ], true));

        UnitsEvent::dispatch(getUnitAuthId(), [
            'queue_id' => $queueItem->id,
            'itemId' => $matchPairId[0],
            'dateTime' => $currentDateTime->format('F j, Y g:i A'),
            'account_id' => $matchPairData['funder_account_id_long']
        ], 'close-position', $matchPairData['platform_type'], $matchPairData['unit_id']);
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

        $queueItem = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('id', $dataFormatted['queue_id'])
            ->first();

        if (empty($queueItem)) {
            return response()->json(['error' => __('Pair queue not found.')]);
        }

        $queueData = maybe_unserialize($queueItem->data);

        $queueData[$dataFormatted['itemId']]['new_equity'] = $dataFormatted['latestEquity'];
        $queueItem->data = maybe_serialize($queueData);
        $queueItem->update();

        unset($queueData[$dataFormatted['itemId']]);

        $matchPairId = array_keys($queueData);
        $matchPairData = $queueData[$matchPairId[0]];

        $tradeAccount = TradeReport::with([
            'tradingAccountCredential',
            'tradingAccountCredential.package',
            'tradingAccountCredential.package.funder',
            'tradingAccountCredential.historyV3'
        ])
            ->where('account_id', auth()->user()->account_id)
            ->where('id', $dataFormatted['itemId'])
            ->first();

        $this->updateLatestEquity($tradeAccount, $dataFormatted['latestEquity'], true);
//        $this->recordTradeHistory($tradeAccount, $dataFormatted['latestEquity']);

        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $matchPairData['unit_id']);

        if (!$isUnitConnected) {
            return response()->json(['error' => __('Unit '. $matchPairData['unit_name'] .' is not connected')]);
        }

        $pairAccount = TradeReport::with('tradingAccountCredential')
            ->where('account_id', auth()->user()->account_id)
            ->where('id', $matchPairId[0])
            ->first();

        // Attempt to close trading position in "Ongoing Trades"
        // tab when both trading accounts are closed.
        $this->closeTradingPositionQueue($tradeAccount, $pairAccount, $queueItem);

        return response()->json($dataFormatted);
    }

    public static function recordTradeHistory($tradeAccount, $latestEquity)
    {
        $latestEquity = (float) $latestEquity;
        $currentPhase = '';

        $tradeHistory = TradeHistoryV3Model::where('trade_account_credential_id', $tradeAccount->trade_account_credential_id)
            ->latest('created_at')
            ->first();

        if (empty($tradeHistory)) {
            info(print_r([
                'recordTradeHistory' => 'fresh'
            ], true));
            self::createTradeHistory($tradeAccount, $latestEquity);
        } else {

            $today = Carbon::today();
            $currentTime = Carbon::now();
            $yesterday = $today->copy()->subDay();
            $today430am = $today->copy()->setTime(4, 31);
            $yesterday430am = $yesterday->copy()->setTime(4, 30);

            $createdAt = Carbon::parse($tradeHistory->created_at);
            $highestBalance = ($latestEquity > $tradeHistory->highest_balance)? $latestEquity : $tradeHistory->highest_balance;

            info(print_r([
                'recordTradeHistory' => 'timeTest',
                'today' => $createdAt->greaterThanOrEqualTo($today430am),
                'yesterday' => ($createdAt->greaterThanOrEqualTo($yesterday430am) && $createdAt->lessThan($today430am) && $currentTime->gt(Carbon::today()->addHours(4)->addMinutes(31)))
            ], true));
            if (
                $createdAt->greaterThanOrEqualTo($today430am) || // Created today past 4:30 AM
                ($createdAt->greaterThanOrEqualTo($yesterday430am) && $createdAt->lessThan($today430am) && $currentTime->gt(Carbon::today()->addHours(4)->addMinutes(31))) // Created yesterday 4:30 AM to today 4:30 AM
            ) {
                $tradeHistory->latest_equity = $latestEquity;
                $tradeHistory->highest_balance = $highestBalance;
                $tradeHistory->update();

                info(print_r([
                    'recordTradeHistory' => 'update',
                ], true));
            } else {
                self::createTradeHistory($tradeAccount, $latestEquity, $highestBalance);
                info(print_r([
                    'recordTradeHistory' => 'create',
                ], true));
            }

        }

        return response()->json(1);
    }

    public static function createTradeHistory($tradeAccount, $latestEquity, $highestBalance = null)
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
            $queueItem->status = 'closed';
            $queueItem->update();

            UnitResponse::dispatch(auth()->user()->account_id, [], 'trade-closed');
        }

        return false;
    }

    private function updateLatestEquity($tradeAccount, $latestEquity, $updateNtrades = false)
    {
        if ($tradeAccount->status !== 'trading') {
            return false;
        }

        $latestEquity = (float) str_replace(' ', '', $latestEquity);
        $tradeAccount->latest_equity = $latestEquity;
        $tradeAccount->status = $this->getStatusByLatestEquity($tradeAccount, $latestEquity);

        if ($updateNtrades) {
            $tradeAccount->n_trades += 1;
        }

//        $highestbal = (float) $report->tradingAccountCredential->historyV3->max('highest_balance');

        $tradeAccount->update();

        return true;
    }

    public static function getCalculatedRdd($data)
    {
        $package = new FunderPackageDataController($data);
        $highestBalArr = [];

        $startingBal = $package->getStartingBalance();
        $latestEqty = (float) $data['latest_equity'];
        $maxDrawdown = $package->getMaxDrawdown();

        if ($package->getDrawdownType() === 'trailing_endofday') {
            if (!empty($data['trading_account_credential']['history_v3'])) {
                foreach ($data['trading_account_credential']['history_v3'] as $tradeItem) {
                    if ($tradeItem['status'] === $package->getPhase()) {
                        $highestBalArr[] = (float) $tradeItem['highest_balance'];
                    }
                }
            }

            $highestBal = (!empty($highestBalArr))? max($highestBalArr) : $latestEqty;
            $bufferZone = $startingBal + $maxDrawdown;

            if ($highestBal >= $bufferZone) {
                return $latestEqty - $startingBal;
            }

            $staticThreshold = $startingBal - $maxDrawdown;
            $maxThreshold = max($highestBal - $maxDrawdown, $staticThreshold);

            return floor($latestEqty - $maxThreshold);
        }

        if ($package->getDrawdownType() === 'static') {
            $maxTreshold = $startingBal - $maxDrawdown;
            return $latestEqty - $maxTreshold;
        }

        return 'N/A';
    }

    private function getStatusByLatestEquity($tradeAccount, $latestEquity)
    {
        $package = new FunderPackageDataController($tradeAccount);
        $tradeAccount = $tradeAccount->toArray();

        $latestEquity = (float) $latestEquity;
        $dailyDrawdown = $package->getDailyDrawdown();
        $dailyTp = $package->getDailyTargetProfit();
        $startingDailyEquity = (float) $tradeAccount['starting_daily_equity'];

        $pnl = $latestEquity - $startingDailyEquity;
        $rdd = self::getCalculatedRdd($tradeAccount);

        if ($rdd !== null && $rdd <= 100) {
            return 'breachedcheck';
        }

        if ($pnl > 0) {
            $dailyTp = $dailyTp * 0.9;
            if ($pnl >= $dailyTp) {
                return 'abstained';
            }
        } else {
            $dailyDrawdown = $dailyDrawdown * 0.9;
            $pnl = -$pnl;
            if ($pnl >= $dailyDrawdown) {
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

        UnitResponse::dispatch(auth()->user()->account_id, [
            'queue_db_id' => $queueItem->id,
            'itemId' => $request->get('itemId')
        ], 'unit-ready');

        if (count($readyUnits) > 1) {
            $queueItem->status = 'pairing';
            $queueItem->pair_status = '';
            UnitResponse::dispatch(auth()->user()->account_id, [
                'queue_db_id' => $queueItem->id,
                'itemId' => $request->get('itemId')
            ], 'initialize-complete');
        }

        $queueItem->update();

        return response()->json(1);
    }

    public function findWaitingProcess()
    {

    }

    public function pairUnits(Request $request)
    {
        $itemIds = $request->all();

        $items = TradeReport::with([
            'tradingAccountCredential',
            'tradingAccountCredential.package',
            'tradingAccountCredential.package.funder',
            'tradingAccountCredential.userAccount.funderAccountCredential',
            'tradingAccountCredential.userAccount.tradingUnit',
            'tradingAccountCredential.historyV3'
        ])
            ->whereIn('id', $itemIds)
            ->get();

        if (empty($items)) {
            return response()->json([]);
        }

        if ($items->count() < 1) {
            return response()->json(['error' => 'Unable to proceed pairing. One of the paired items no longer exists.']);
        }

        $pairLimits = new PairLimitsController($items);
        $pairLimits = $pairLimits->getLimits();

        $data = [];

        foreach ($items->toArray() as $item) {

            $package = new FunderPackageDataController($item);

            $dailyEquity = (float) $item['starting_daily_equity'];
            $latestEquity = (float) $item['latest_equity'];
            $pnl = $latestEquity - $dailyEquity;

            $credential = getFunderAccountCredential($item);

            $dailyDrawdown = $package->getDailyDrawdown();
            $maxDrawDown = $package->getMaxDrawdown();
            $dailyTargetProfit  = $package->getDailyTargetProfit();

            if (empty($dailyDrawdown)) {
                $dailyDrawdown = $package->getStartingBalance() * 0.015; // default daily drawdown
            }

            if ($pnl < 0) {
                $dailyDrawdown = $pnl + $dailyDrawdown;
            }

            $rdd = self::getCalculatedRdd($item);

            $data[$item['id']] = [
                'id' => $item['id'],
                'funder' => $package->getFunderAlias(),
                'funder_theme' => $package->getFunderTheme(),
                'funder_account_id_long' => $item['trading_account_credential']['funder_account_id'],
                'funder_account_id_short' => getFunderAccountShortName($item['trading_account_credential']['funder_account_id']),
                'unit_name' => $item['trading_account_credential']['user_account']['trading_unit']['name'],
                'unit_id' => $item['trading_account_credential']['user_account']['trading_unit']['unit_id'],
                'starting_balance' => $package->getStartingBalance(),
                'starting_equity' => $item['starting_daily_equity'],
                'latest_equity' => $item['latest_equity'],
                'pnl' => number_format($pnl, 2),
                'rdd' => (is_numeric($rdd))? round($rdd, 0) : $rdd,
                'symbol' => $package->getSymbol(),
                'order_amount' => $pairLimits[$item['id']]['tp']['lots'],
                'tp' => $pairLimits[$item['id']]['tp']['ticks'],
                'convertedTp' => $pairLimits[$item['id']]['tp']['amount'],
                'sl' => $pairLimits[$item['id']]['sl']['ticks'],
                'convertedSl' => $pairLimits[$item['id']]['sl']['amount'],
                'remaining_target_profit' => getRemainingTargetProfit($item),
                'daily_target_profit' => $dailyTargetProfit,
                'asset_type' => $package->getAssetType(),
                'daily_draw_down' => $dailyDrawdown,
                'max_draw_down' => $maxDrawDown,
                'package_tag' => $item['trading_account_credential']['package']['name'],
                'platform_type' => $package->getPlatformType(),
                'login_username' => (!empty($item['trading_account_credential']['platform_login_username']))? $item['trading_account_credential']['platform_login_username'] : $credential['loginUsername'],
                'login_password' => (!empty($item['trading_account_credential']['platform_login_password']))? $item['trading_account_credential']['platform_login_password'] : $credential['loginPassword'],
                'phase' => $package->getPhase(),
                'data' => $item
            ];
        }

        return response()->json($data);
    }

    public function pairUnits_old(Request $request)
    {
        $itemIds = $request->all();

        $items = TradeReport::with([
            'tradingAccountCredential',
            'tradingAccountCredential.package',
            'tradingAccountCredential.package.funder',
            'tradingAccountCredential.userAccount.funderAccountCredential',
            'tradingAccountCredential.userAccount.tradingUnit',
            'tradingAccountCredential.historyV3'
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

            $package = new FunderPackageDataController($item);

            $dailyEquity = (float) $item['starting_daily_equity'];
            $latestEquity = (float) $item['latest_equity'];
            $pnl = $latestEquity - $dailyEquity;

            $credential = getFunderAccountCredential($item);

            $dailyDrawdown = $package->getDailyDrawdown();
            $maxDrawDown = $package->getMaxDrawdown();
            $dailyTargetProfit  = $package->getDailyTargetProfit();

            if (empty($dailyDrawdown)) {
                $dailyDrawdown = $package->getStartingBalance() * 0.015; // default daily drawdown
            }

            if ($pnl < 0) {
                $dailyDrawdown = $pnl + $dailyDrawdown;
            }

            $rdd = self::getCalculatedRdd($item);

            $data[$item['id']] = [
                'id' => $item['id'],
                'funder' => $package->getFunderAlias(),
                'funder_theme' => $package->getFunderTheme(),
                'funder_account_id_long' => $item['trading_account_credential']['funder_account_id'],
                'funder_account_id_short' => getFunderAccountShortName($item['trading_account_credential']['funder_account_id']),
                'unit_name' => $item['trading_account_credential']['user_account']['trading_unit']['name'],
                'unit_id' => $item['trading_account_credential']['user_account']['trading_unit']['unit_id'],
                'starting_balance' => $package->getStartingBalance(),
                'starting_equity' => $item['starting_daily_equity'],
                'latest_equity' => $item['latest_equity'],
                'pnl' => number_format($pnl, 2),
                'rdd' => (is_numeric($rdd))? round($rdd, 0) : $rdd,
                'symbol' => $package->getSymbol(),
                'order_amount' => TradePairAccountsController::getCalculatedOrderAmountV2($item, $package->getAssetType()),
                'tp' => TradePairAccountsController::getTakeProfitTicks($package->getAssetType()),
                'sl' => TradePairAccountsController::getStopLossTicks($package->getAssetType()),
                'remaining_target_profit' => getRemainingTargetProfit($item),
                'daily_target_profit' => $dailyTargetProfit,
                'asset_type' => $package->getAssetType(),
                'daily_draw_down' => $dailyDrawdown,
                'max_draw_down' => $maxDrawDown,
                'package_tag' => $item['trading_account_credential']['package']['name'],
                'platform_type' => $package->getPlatformType(),
                'login_username' => $credential['loginUsername'],
                'login_password' => $credential['loginPassword'],
                'phase' => $package->getPhase(),
                'data' => $item
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
        $futureTime = $currentUtcTime->addSeconds(20); // seconds of delay allowance before trade button click.

        foreach ($data as $unitTradeItem) {
            $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $unitTradeItem['unit_id']);

            if (!$isUnitConnected) {
                return response()->json([
                    'error' => 'Unit '. $unitTradeItem['unit_name'] .' is not connected.'
                ]);
            }
        }

        $purchaseTypeOverrides = $request->get('purchase_type');

        foreach ($data as $unitId => $unitItem) {

            $pairItem = TradeReport::where('account_id', auth()->user()->account_id)
                ->where('id', $unitId)
                ->first();

            $pairItem->status = 'trading';
            $pairItem->update();

            $purchaseType = (!empty($purchaseTypeOverrides[$unitId]))? $purchaseTypeOverrides[$unitId] : $unitItem['purchase_type'];

            $formattedTime = Carbon::now()->format('M. d, Y g:i:s a');

            $args = [
                'year' => $futureTime->format('Y'),
                'month' => $futureTime->format('m'),
                'day' => $futureTime->format('d'),
                'hours' => $futureTime->format('H'),
                'minutes' => $futureTime->format('i'),
                'seconds' => $futureTime->format('s'),
                'purchase_type' => $purchaseType,
                'queue_id' => $queueItem->id,
                'itemId' => $unitId,
                'order_amount' => $unitItem['order_amount'],
                'take_profit_ticks' => $unitItem['tp'],
                'stop_loss_ticks' => $unitItem['sl'],
                'funder' => [
                    'alias' => $unitItem['funder'],
                    'theme' => $unitItem['funder_theme']
                ],
                'timeTradeSent' => $formattedTime,
                'account_id' => $unitItem['funder_account_id_long'],
                'account_id_short' => $unitItem['funder_account_id_short'],
                'loginUsername' => $unitItem['login_username'],
                'loginPassword' => $unitItem['login_password'],
            ];

            $data[$unitId]['purchase_type'] = $purchaseType;

            UnitsEvent::dispatch(getUnitAuthId(), $args, 'do-trade', $unitItem['platform_type'], $unitItem['unit_id']);
        }

        $queueItem->data = maybe_serialize($data);
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

    public function updateUnitProcess($item, $processType)
    {
        $process = $this->getUnitProcess($item, $processType);

        if (!empty($process)) {
            $process->delete();
        }

        $nextProcess = $this->getUnitProcess($item, $processType, 'waiting');
    }

    public function getUnitProcess($item, $processType, $status = 'processing')
    {
        return UnitProcessesModel::where('account_id', auth()->user()->account_id)
            ->where('unit_id', $item['unit_id'])
            ->where('process_type', $processType)
            ->where('status', $status)
            ->first();
    }

    public function recordUnitProcess($item, $queueDbId, $processType)
    {
        $existingProcess = $this->getUnitProcess($item, $processType);

        $process = new UnitProcessesModel();
        $process->account_id = auth()->user()->account_id;
        $process->unit_id = $item['unit_id'];
        $process->status = 'waiting';
        $process->process_name = $item['platform_type'];
        $process->process_type = $processType;
        $process->queue_id = $queueDbId;

        if (empty($existingProcess)) {
            $process->status = 'processing';
        }

        $process->save();

        return $process->status === 'processing';
    }

    public function initiateTradeV2(Request $request)
    {
        $data = $request->get('data');
        $status = $request->get('status');

        if (empty($status)) {
            $status = 'pairing';
        }

        foreach ($data as $unitTradeItem) {
            $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $unitTradeItem['unit_id']);

            if (!$isUnitConnected) {
                return response()->json([
                    'error' => 'Unit '. $unitTradeItem['unit_name'] .' is not connected.'
                ]);
            }
        }

        /**
         * @todo
         * Add check if items are already paired
         */

        if (empty($request->get('queueItem'))) {
            $queueId = $this->generateQueueId();

            $tradeQueue = new TradeQueueModel();
            $tradeQueue->account_id = auth()->user()->account_id;
            $tradeQueue->queue_id = $queueId;
            $tradeQueue->data = maybe_serialize($data);
            $tradeQueue->status = $status;
            $tradeQueue->save();

        } else {
            $tradeQueue = $request->get('queueItem');
            $tradeQueue->status = $status;
            $tradeQueue->update();

            $queueId = $tradeQueue->queue_id;
        }

        $dispatchCommand = $request->get('dispatchCommand');

        if ($dispatchCommand !== false) {
            $dispatchCommand = true;
        }

        /**
         * @todo remove after test
         */
//        $dispatchCommand = false;

        foreach ($data as $itemId => $item) {
            $this->initiateUnitTrade($itemId, $item, $queueId, $tradeQueue->id, $status, $dispatchCommand);
        }

        WebPush::dispatch(auth()->user()->account_id, ['ids' => array_keys($data)], 'pair-units');

        return response()->json([
            'message' => __('Account pairing initiated.')
        ]);
    }

    private function initiateUnitTrade($itemId, $item, $queueId, $tradeQueueId, $status = 'pairing', $dispatchCommand = false)
    {
        $pairUnit = TradeReport::where('id', $itemId)->first();
        $pairUnit->status = $status;
        $pairUnit->update();

        if ($dispatchCommand) {
            $this->dispatchUnitTrade($item, $itemId, $queueId, $tradeQueueId);
        }
    }

    private function dispatchUnitTrade($item, $itemId, $queueId, $tradeQueueId)
    {
        UnitsEvent::dispatch(getUnitAuthId(), [
            'account_id' => $item['funder_account_id_long'],
            'account_id_short' => $item['funder_account_id_short'],
            'purchase_type' => $item['purchase_type'],
            'symbol' => $item['symbol'],
            'order_amount' => $item['order_amount'],
            'take_profit_ticks' => $item['tp'],
            'stop_loss_ticks' => $item['sl'],
            'queue_id' => $queueId,
            'queue_db_id' => $tradeQueueId,
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
