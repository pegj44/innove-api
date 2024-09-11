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
    public function pairAccounts(Request $request)
    {
        $accounts = self::getTradableAccounts();

        if (empty($accounts)) {
            return response()->json(['message' => 'No available accounts to trade']);
        }

        $user_id = auth()->id();

        AccountsPairingJob::where('user_id', $user_id)->delete(); // Reset record

        foreach ($accounts as $account) {

            $this->updateEquityUpdateStatus($user_id, $account['trade_credential']['account_id'], 'pairing');

            UnitsEvent::dispatch(
                $user_id,
                [
                    'account_id' => $account['trade_credential']['account_id']
                ],
                'update-starting-equity',
                $account['trade_credential']['funder']['alias'] .'_'. $account['trade_credential']['account_id'],
                $account['trade_credential']['trading_individual']['trading_unit']['ip_address']);
        }

        return $accounts;
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
        return TradeReport::with('tradeCredential.tradingIndividual.tradingUnit', 'tradeCredential.funder', 'tradeCredential.funder.metadata')
            ->where('user_id', auth()->id())
            ->where('status', 'idle')
            ->get()
            ->toArray();
    }

    public function setAccountPurchaseType(Request $request)
    {
        $funderMeta = FundersMetadata::where('funder_id', $request->get('funder_id'))
            ->where('key', 'purchase_type')->first();
info(print_r([
    'funderMetadata' => $funderMeta
], true));
        if ($funderMeta) {
            $funderMeta->value = [$request->get('purchase_type')];
            $updated = $funderMeta->update();

            info(print_r([
                'request' => $request->all(),
                'isUpdated' => $updated
            ], true));
        } else {
            $funderMeta = new FundersMetadata();
            $funderMeta->funder_id = $request->get('funder_id');
            $funderMeta->key = 'purchase_type';
            $funderMeta->value = [$request->get('purchase_type')];
            $funderMeta->save();
        }

        return response()->json([
            'received_data' => $request->all()
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
        $pnl1 = self::calculatePnL($item1);
        $pnl2 = self::calculatePnL($item2);

        if (($pnl1 > 0 && $pnl2 > 0) || ($pnl1 < 0 && $pnl2 < 0)) {
            $distance = abs($pnl1 - $pnl2) + abs(floatval($item1['latest_equity']) - floatval($item2['latest_equity']));
        } else {
            $distance = PHP_FLOAT_MAX;
        }

        if ($pnl1 == 0 || $pnl2 == 0) {
            $distance = abs(floatval($item1['latest_equity']) - floatval($item2['latest_equity'])) + abs(floatval($item1['starting_balance']) - floatval($item2['starting_balance']));
        }

        return $distance;
    }

    public static function pairItems()
    {
        $pairingItems = AccountsPairingJob::where('user_id', auth()->id())
            ->where('status', 'pairing')
            ->get();

        if ($pairingItems->isNotEmpty()) {
//            UnitResponse::dispatch(auth()->id(), 'pair_accounts-failed');
            return false;
        }

        $items = self::getTradableAccounts();

        if (empty($items)) {
            UnitResponse::dispatch(auth()->id(), 'no-pairable-accounts');
            return false;
        }

        $paired = [];
        $used_indices = [];

        foreach ($items as $i => $item1) {
            if (in_array($i, $used_indices)) continue;

            $closest_index = null;
            $closest_distance = PHP_FLOAT_MAX;

            foreach ($items as $j => $item2) {
                if ($i == $j || in_array($j, $used_indices)) continue;
                if (!self::matchByPhase($item1, $item2)) continue;

                $distance = self::matchByPnL($item1, $item2);
                info(print_r([
                    '$distance' => $distance,
                    '$closest_distance' => $closest_distance
                ], true));
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

        if (!empty($paired)) {
            self::savePairedItems($paired);

            UnitResponse::dispatch(auth()->id(), 'pair_accounts-success');
        }

        return $paired;
    }

    public function getPairedItems()
    {
        $pairedItems = PairedItems::with([
            'tradeReportPair1.tradeCredential.tradingIndividual.tradingUnit',
            'tradeReportPair1.tradeCredential.funder.metadata',
            'tradeReportPair2.tradeCredential.tradingIndividual.tradingUnit',
            'tradeReportPair2.tradeCredential.funder.metadata'
        ])
        ->where('user_id', auth()->id())
        ->get();

        return response()->json($pairedItems);
    }

    public static function savePairedItems($items)
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

    public function clearPairedItems()
    {
        $pairedItems = PairedItems::where('user_id', auth()->id());
        $pairedItems->delete();

        return response()->json(['message' => __('Successfully cleared items.')]);
    }
}
