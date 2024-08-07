<?php

namespace App\Http\Controllers;

use App\Models\PairedItems;
use App\Models\TradeReport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TradePairAccountsController extends Controller
{
    public function pairAccounts()
    {
        $items = TradeReport::with('tradeCredential.tradingIndividual', 'tradeCredential.funder')
            ->where('user_id', auth()->id())
            ->where('status', 'idle')
            ->get()
            ->toArray();

        $pairedItems = $this->pairItems($items);

        if (!empty($pairedItems)) {
            $this->savePairedItems($pairedItems);
        }

        return response()->json($pairedItems);
    }

    public function calculatePnL($item) {
        return floatval($item['latest_equity']) - floatval($item['starting_equity']);
    }

    public function matchByPhase($item1, $item2)
    {
        return $item1['trade_credential']['phase'] === $item2['trade_credential']['phase'];
    }

    public function matchByPnL($item1, $item2)
    {
        $pnl1 = $this->calculatePnL($item1);
        $pnl2 = $this->calculatePnL($item2);

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

    public function pairItems($items)
    {
        $paired = [];
        $used_indices = [];

        foreach ($items as $i => $item1) {
            if (in_array($i, $used_indices)) continue;

            $closest_index = null;
            $closest_distance = PHP_FLOAT_MAX;

            foreach ($items as $j => $item2) {
                if ($i == $j || in_array($j, $used_indices)) continue;
                if (!$this->matchByPhase($item1, $item2)) continue;

                $distance = $this->matchByPnL($item1, $item2);
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

    public function savePairedItems($items)
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
