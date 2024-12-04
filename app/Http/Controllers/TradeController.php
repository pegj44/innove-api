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

        $errors[$itemId] = $errorMessage;

        $queueItem->errors = maybe_serialize($errors);
        $queueItem->status = 'error';

        $queueItem->update();

        UnitResponse::dispatch(auth()->user()->account_id, [
            'queue_db_id' => $tradeQueueId,
            'itemId' => $itemId,
            'message' => __('Failed to initialize'),
            'sound' => false
        ], 'trade-initialize-error');

        return response()->json($queueItem);
    }

    /**
     * Just let the pair know the trade is closed.
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

        if (in_array($matchPairId[0], $closedItems)) {
            return false; // Pair already closed, not need to report.
        }

        $queueItem->closed_items = maybe_serialize([$request->get('itemId')]); // save closed item.
        $queueItem->update();

        $matchPairData = $queueData[$matchPairId[0]];

        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $matchPairData['unit_id']);

        if (!$isUnitConnected) {
            return response()->json(['error' => __('Unit '. $matchPairData['unit_id'] .' is not connected')]);
        }

        UnitsEvent::dispatch(getUnitAuthId(), [
            'queue_id' => $queueItem->id,
            'itemId' => $matchPairId[0],
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
//        $itemData = $queueData[$dataFormatted['itemId']];

        unset($queueData[$dataFormatted['itemId']]);

        $matchPairId = array_keys($queueData);
        $matchPairData = $queueData[$matchPairId[0]];

        $tradeAccount = TradeReport::with('tradingAccountCredential')
            ->where('account_id', auth()->user()->account_id)
            ->where('id', $dataFormatted['itemId'])
            ->first();

        $this->updateLatestEquity($tradeAccount, $dataFormatted['latestEquity'], true);
//        $this->recordTradeHistory($tradeAccount, $dataFormatted['latestEquity']);

        $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $matchPairData['unit_id']);

        if (!$isUnitConnected) {
            return response()->json(['error' => __('Unit '. $matchPairData['unit_id'] .' is not connected')]);
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

    private function recordTradeHistory($tradeAccount)
    {

    }

    private function recordTradeHistory_old($tradeAccount, $latestEquity)
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

        $tradeAccount->update();

        return true;
    }

    public static function getCalculatedRdd($data)
    {
        $currentPhase = str_replace('phase-', '', $data['trading_account_credential']['current_phase']);
        $highestBalArr = [];

        $startingBal = (float) $data['trading_account_credential']['starting_balance'];
        $latestEqty = (float) $data['latest_equity'];
        $maxDrawdown = (float) $data['trading_account_credential']['phase_'. $currentPhase .'_max_drawdown'];

        if ($data['trading_account_credential']['drawdown_type'] === 'trailing_endofday') {
            if (!empty($data['trading_account_credential']['history_v3'])) {
                foreach ($data['trading_account_credential']['history_v3'] as $tradeItem) {
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

        if ($data['trading_account_credential']['drawdown_type'] === 'static') {
            $maxTreshold = $startingBal - $maxDrawdown;
            return $latestEqty - $maxTreshold;
        }

        return 'N/A';
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

        $rdd = self::getCalculatedRdd($tradeAccount);

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

        if (count($readyUnits) > 1) {
            $queueItem->status = 'pairing';
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

            $currentPase = str_replace('phase-', '', $item['trading_account_credential']['current_phase']);
            $dailyDrawdown = (float) $item['trading_account_credential']['phase_'. $currentPase .'_daily_drawdown'];
            $maxDrawDown = (float) $item['trading_account_credential']['phase_'. $currentPase .'_max_drawdown'];

            if (empty($dailyDrawdown)) {
                $dailyDrawdown = (float) $item['trading_account_credential']['starting_balance'] * 0.015; // default daily drawdown
            }

            if ($pnl < 0) {
                $dailyDrawdown = $pnl + $dailyDrawdown;
            }

            $data[$item['id']] = [
                'id' => $item['id'],
                'funder' => $item['trading_account_credential']['funder']['alias'],
                'funder_theme' => $item['trading_account_credential']['funder']['theme'],
                'funder_account_id_long' => $item['trading_account_credential']['funder_account_id'],
                'funder_account_id_short' => getFunderAccountShortName($item['trading_account_credential']['funder_account_id']),
                'unit_name' => $item['trading_account_credential']['user_account']['trading_unit']['name'],
                'unit_id' => $item['trading_account_credential']['user_account']['trading_unit']['unit_id'],
                'starting_balance' => (float) $item['trading_account_credential']['starting_balance'],
                'starting_equity' => $item['starting_daily_equity'],
                'latest_equity' => $item['latest_equity'],
                'pnl' => number_format($pnl, 2),
                'rdd' => round(self::getCalculatedRdd($item), 0),
                'symbol' => $item['trading_account_credential']['symbol'],
                'order_amount' => TradePairAccountsController::getCalculatedOrderAmountV2($item, $item['trading_account_credential']['asset_type']),
                'tp' => TradePairAccountsController::getTakeProfitTicks($item),
                'sl' => TradePairAccountsController::getStopLossTicks($item),
                'asset_type' => $item['trading_account_credential']['asset_type'],
                'daily_draw_down' => $dailyDrawdown,
                'max_draw_down' => $maxDrawDown,
                'platform_type' => $item['trading_account_credential']['platform_type'],
                'login_username' => $credential['loginUsername'],
                'login_password' => $credential['loginPassword'],
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
        $futureTime = $currentUtcTime->addSeconds(3); // seconds of delay allowance before trade button click.

        foreach ($data as $unitTradeItem) {
            $isUnitConnected = PusherController::checkUnitConnection(getUnitAuthId(), $unitTradeItem['unit_id']);

            if (!$isUnitConnected) {
                return response()->json([
                    'error' => 'Unit '. $unitTradeItem['unit_id'] .' is not connected.'
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
                'funder' => [
                    'alias' => $unitItem['funder'],
                    'theme' => $unitItem['funder_theme']
                ],
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

    public function recordUnitProcess($item, $processType)
    {
        $existingProcess = $this->getUnitProcess($item, $processType);

        $process = new UnitProcessesModel();
        $process->account_id = auth()->user()->account_id;
        $process->unit_id = $item['unit_id'];
        $process->status = 'waiting';
        $process->process_name = $item['platform_type'];
        $process->process_type = $processType;

        if (empty($existingProcess)) {
            $process->status = 'processing';
        }

        $process->save();

        return $process->status === 'processing';
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
            $isProcessRecorded = $this->recordUnitProcess($item, 'initiate-trade');
            $this->initiateUnitTrade($itemId, $item, $queueId, $tradeQueue->id, $isProcessRecorded);
        }

        return response()->json(['message' => __('Account pairing initiated.')]);
    }

    private function initiateUnitTrade($itemId, $item, $queueId, $tradeQueueId, $initiateTrade = false)
    {
        $pairUnit = TradeReport::where('id', $itemId)->first();
        $pairUnit->status = 'pairing';
        $pairUnit->update();

        if ($initiateTrade) {
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
