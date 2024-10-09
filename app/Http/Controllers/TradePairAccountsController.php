<?php

namespace App\Http\Controllers;

use App\Events\UnitResponse;
use App\Events\UnitsEvent;
use App\Models\AccountsPairingJob;
use App\Models\FundersMetadata;
use App\Models\PairedItems;
use App\Models\TradeReport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TradePairAccountsController extends Controller
{
    public static $takeProfitTicks = 49;
    public static $stopLossTicks = 51;
    public static $breachAllowanceAmount = 50;

    public function pairManual(Request $request)
    {
        $ids = explode(',', $request->get('paired-ids'));

        $pairedItems = new PairedItems();
        $pairedItems->account_id = auth()->user()->account_id;
        $pairedItems->pair_1 = $ids[0];
        $pairedItems->pair_2 = $ids[1];
        $pairedItems->status = 'pairing';
        $pairedItems->save();

        $item1 = TradeReport::where('id', $ids[0])->first();
        $item1->status = 'pairing';
        $item1->update();

        $item2 = TradeReport::where('id', $ids[1])->first();
        $item2->status = 'pairing';
        $item2->update();

        return response()->json([
            'pairedId' => $pairedItems->id
        ]);
    }

    public function removePair(Request $request, string $id)
    {
        $item = PairedItems::where('id', $id)->first();

        $item1 = TradeReport::where('id', $request->get('pair1'))->first();
        $item1->status = 'idle';
        $item1->update();

        $item2 = TradeReport::where('id', $request->get('pair2'))->first();
        $item2->status = 'idle';
        $item2->update();

        if ($request->get('updateStatus')) {
            $item->delete();
        } else {
            $item->status = 'pairing';
            $item->update();
        }

        return response()->json(['id' => $id]);
    }

    public function pairAccounts(Request $request)
    {
        $items = self::getTradableAccounts();

        if (empty($items)) {
            return response()->json([
                'data' => [],
                'message' => __('No available accounts to trade.')
            ]);
        }

        $paired = [];
        $used_indices = [];

        foreach ($items as $i => $item1) {
            if (self::isBreached($item1)) continue;
            if (self::isAbstained($item1)) continue;
            if (in_array($i, $used_indices)) continue;

            $closest_index = null;
            $closest_distance = PHP_FLOAT_MAX;

            foreach ($items as $j => $item2) {
                if (self::isBreached($item2)) continue;
                if (self::isAbstained($item2)) continue;
                if ($i == $j || in_array($j, $used_indices)) continue;
                if (!self::matchByPhase($item1, $item2)) continue;

                $distance = self::matchByPnL($item1, $item2);

                if ($distance < $closest_distance) {
                    $closest_index = $j;
                    $closest_distance = $distance;
                }
            }

            if ($closest_index !== null) {
                $paired[] = [$item1, $items[$closest_index]];
                $used_indices[] = $i;
                $used_indices[] = $closest_index;
            }
        }

        if (empty($paired)) {
            return response()->json([
                'data' => [],
                'message' => __('No matching accounts found.')
            ]);
        }

        self::savePairedItems($paired);

        return response()->json(['message' => '', 'data' => $paired]);
    }

    public static function updateEquityUpdateStatus($user_id, $account_id, $status)
    {
        if ($status === 'pairing') {
            $account = new AccountsPairingJob();
            $account->account_id = $account_id;
            $account->user_id = $user_id;
            $account->status = $status;
            $account->save();
        } else {
            $account = AccountsPairingJob::where('user_id', $user_id)
                ->where('account_id', $account_id)->first();

            $account->account_id = $account_id;
            $account->user_id = $user_id;
            $account->status = $status;
            $account->update();
        }

        return $account->account_id;
    }

    public static function getTradableAccounts()
    {
        return TradeReport::with('tradingAccountCredential.userAccount.tradingUnit', 'tradingAccountCredential.funder', 'tradingAccountCredential.funder.metadata')
            ->where('user_id', auth()->id())
            ->where('status', 'idle')
            ->get()
            ->toArray();
    }

    public function updateTradeSettings(Request $request)
    {
        $tradeReport = TradeReport::where('id', $request->get('trade_report_id'))->first();

        if (empty($tradeReport)) {
            return response()->json(['error' => __('Trade report item not found.')]);
        }

        $args = parseArgs($request->except('_token'), [
            'order_amount' => 1,
            'stop_loss_ticks' => 1,
            'take_profit_ticks' => 1
        ]);

        $tradeReport->purchase_type = $args['purchase_type'];
        $tradeReport->order_amount = $args['order_amount'];
        $tradeReport->stop_loss_ticks = $args['stop_loss_ticks'];
        $tradeReport->take_profit_ticks = $args['take_profit_ticks'];
        $tradeReport->update();

        if ($request->get('trade_report_pair_id') !== null) {
            $tradeReportPair = TradeReport::where('id', $request->get('trade_report_pair_id'))->first();

            if (!empty($tradeReportPair)) {
                $purchaseType = ($request->get('purchase_type') === 'sell')? 'buy' : 'sell';
                $tradeReportPair->purchase_type = $purchaseType;
                $tradeReportPair->update();
            }
        }

        return response()->json([
            '$tradeReport' => $tradeReport
        ]);
    }

    private static function calculatePnL($item)
    {
        return floatval($item['latest_equity']) - floatval($item['starting_equity']);
    }

    private static function matchByPhase($item1, $item2)
    {
        return $item1['trade_credential']['phase'] === $item2['trade_credential']['phase'];
    }

    private static function matchByPnL($item1, $item2)
    {

    }

    private static function matchByTargetProfit($item1, $item2)
    {

    }

    private static function isAbstained($item)
    {
        return false;
    }

    private static function isBreached($item)
    {
        info(print_r([
            'isBreached' => $item
        ], true));





        return false;
    }

//    private static function matchByPnL($item1, $item2)
//    {
//        $pnl1 = self::calculatePnL($item1);
//        $pnl2 = self::calculatePnL($item2);
//
//        if (($pnl1 > 0 && $pnl2 > 0) || ($pnl1 < 0 && $pnl2 < 0)) {
//            $distance = abs($pnl1 - $pnl2) + abs(floatval($item1['latest_equity']) - floatval($item2['latest_equity']));
//        } else {
//            $distance = PHP_FLOAT_MAX;
//        }
//
//        if ($pnl1 == 0 || $pnl2 == 0) {
//            $distance = abs(floatval($item1['latest_equity']) - floatval($item2['latest_equity'])) + abs(floatval($item1['starting_balance']) - floatval($item2['starting_balance']));
//        }
//
//        return $distance;
//    }

    public function getPairedItems()
    {
        $pairedItems = PairedItems::with([
            'tradeReportPair1.tradingAccountCredential.userAccount.tradingUnit',
            'tradeReportPair1.tradingAccountCredential.funder.metadata',
            'tradeReportPair2.tradingAccountCredential.userAccount.tradingUnit',
            'tradeReportPair2.tradingAccountCredential.funder.metadata'
        ])
        ->where('account_id', auth()->user()->account_id)
        ->get();

        return response()->json($pairedItems);
    }

    private static function savePairedItems($items)
    {
        $pairedItems = [];
        $userId = auth()->id();
        $currentTime = Carbon::now();

        foreach ($items as $item) {
            $pairedItems[] = [
                'user_id' => $userId,
                'pair_1' => $item[0]['id'],
                'pair_2' => $item[1]['id'],
                'status' => 'paired',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ];

            $itemMetadata1 = [];
            $itemMetadata2 = [];

            foreach ($item[0]['trade_credential']['funder']['metadata'] as $funderMeta) {
                $itemMetadata1[$funderMeta['key']] = $funderMeta['value'];
            }

            foreach ($item[1]['trade_credential']['funder']['metadata'] as $funderMeta) {
                $itemMetadata2[$funderMeta['key']] = $funderMeta['value'];
            }

            TradeReport::where('id', $item[0]['id'])->update([
                'order_amount' => self::getCalculatedOrderAmount($itemMetadata1['consistency_rule'], $itemMetadata1['consistency_rule_type'], $item[0]['latest_equity'], $item[0]['starting_balance']),
                'take_profit_ticks' => 49,
                'stop_loss_ticks' => 51,
                'status' => 'pairing',
                'purchase_type' => 'buy',
            ]);
            TradeReport::where('id', $item[1]['id'])->update([
                'order_amount' => self::getCalculatedOrderAmount($itemMetadata2['consistency_rule'], $itemMetadata2['consistency_rule_type'], $item[1]['latest_equity'], $item[1]['starting_balance']),
                'take_profit_ticks' => 49,
                'stop_loss_ticks' => 51,
                'status' => 'pairing',
                'purchase_type' => 'sell'
            ]);
        }

        $existingItems = PairedItems::whereIn('pair_1', array_column($pairedItems, 'pair_1'))
            ->whereIn('pair_2', array_column($pairedItems, 'pair_2'))
            ->get(['pair_1', 'pair_2'])
            ->toArray();

        $filteredData = array_filter($pairedItems, function ($item) use ($existingItems) {
            foreach ($existingItems as $existingItem) {
                if ($existingItem['pair_1'] == $item['pair_1'] && $existingItem['pair_2'] == $item['pair_2']) {
                    return false; // Existing item found, exclude from insert
                }
            }
            return true;
        });

        PairedItems::insert($filteredData);
    }

    public static function getCalculatedOrderAmount($consistencyRule, $consistencyRuleType, $latestEquity, $startingEquity, $outputType = 'ticks')
    {
        $consistencyRule = (integer) $consistencyRule;
        $latestEquity = (integer) $latestEquity;
        $startingEquity = (integer) $startingEquity;

        $consistencyAmount = ($consistencyRuleType === 'fixed')? $consistencyRule : ($consistencyRule / 100) * $startingEquity;
        $idealAmount = $startingEquity + $consistencyAmount;
        $takeProfit = $consistencyAmount;

        if ($latestEquity > $startingEquity && $latestEquity < $idealAmount) {
            $takeProfit = $idealAmount - $latestEquity;
        }

        if ($outputType === 'ticks') {
            return floor($takeProfit / self::$takeProfitTicks);
        }

        return $takeProfit;
    }

    public function clearPairedItems()
    {
        PairedItems::where('user_id', auth()->id())
            ->where('status', 'paired')
            ->delete();

        $pairingItems = TradeReport::where('user_id', auth()->id())
            ->where('status', 'pairing')
            ->get();

        foreach ($pairingItems as $item) {
            $item->status = 'idle';
            $item->update();
        }

        return response()->json(['message' => __('Successfully cleared items.')]);
    }
}
