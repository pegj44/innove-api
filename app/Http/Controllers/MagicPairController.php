<?php

namespace App\Http\Controllers;

use App\Models\TradeReport;
use App\Models\TradingAccountCredential;
use App\Models\TradingIndividual;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MagicPairController extends Controller
{
    public $accountId;
    public $pairableItems;
    public $pairedItems = [];
    public $brackets = [
        [20000, 59000],
        [60000, 89000],
        [90000, 150000],
    ];

    protected $items;
    protected $pairedIds = [];

    public function magicPairAccounts(Request $request)
    {
        $this->accountId = $request->get('account_id');

        if (empty($this->accountId)) {
            $this->accountId = auth()->user()->account_id;
        }

        $items = $this->getPairableAccounts();

        if (empty($items)) {
            return response()->json(['message' => 'No pairable items']);
        }

        $this->items = collect($items);
        $pairedItems = $this->findBestPairs();

        $minRandom = 46;
        $maxRandom = 52;
        $baseStopLoss = rand($minRandom, $maxRandom);


        foreach ($pairedItems as $pair) {
            $pairItem1Credential = getFunderAccountCredential($pair['item']);
            $pairData = [];
            $lowestDrawdownObj = [];
            $priority = [];

            foreach ($pair as $pairItem) {
                $currentPhase = str_replace('phase-', '', $pairItem['trading_account_credential']['current_phase']);
                $dailyDrawDown = (float) $pairItem['trading_account_credential']['phase_'. $currentPhase .'_daily_drawdown'];
                $startingBalance = (float) $pairItem['trading_account_credential']['starting_balance'];
                $maxDrawDown = (float) $pairItem['trading_account_credential']['phase_'. $currentPhase .'_max_drawdown'];
                $latestEquity = (float) $pairItem['latest_equity'];
                $drawDownHandler = $startingBalance - $maxDrawDown;

                $maxDrawDown = $latestEquity - $drawDownHandler;
                $lowestDrawdown = ($maxDrawDown < $dailyDrawDown)? $maxDrawDown : $dailyDrawDown;
                $lowestDrawdownObj[$lowestDrawdown] = $pairItem['id'];
                $priority[$pairItem['trading_account_credential']['priority']] = $pairItem['id'];

                $pairData[$pairItem['id']] = [
                    'symbol' => $pairItem['trading_account_credential']['symbol'],
                    'order_amount' => '',
                    'tp' => '',
                    'sl' => '',
                    'purchase_type' => '',
                    'unit_id' => $pairItem['trading_account_credential']['user_account']['trading_unit']['unit_id'],
                    'platform_type' => $pairItem['trading_account_credential']['platform_type'],
                    'login_username' => $pairItem1Credential['loginUsername'],
                    'login_password' => $pairItem1Credential['loginPassword'],
                    'funder_account_id_long' => $pairItem['trading_account_credential']['funder_account_id'],
                    'funder_account_id_short' => getFunderAccountShortName($pairItem['trading_account_credential']['funder_account_id']),
                    'funder' => $pairItem['trading_account_credential']['funder']['alias'],
                    'funder_theme' => $pairItem['trading_account_credential']['funder']['theme'],
                    'unit_name' => $pairItem['trading_account_credential']['user_account']['trading_unit']['name'],
                    'starting_balance' => $startingBalance,
                    'starting_equity' => $pairItem['starting_daily_equity'],
                    'latest_equity' => $latestEquity,
                    'rdd' => TradeController::getCalculatedRdd($pairItem),
                ];
            }

            $lowestKey = min(array_keys($lowestDrawdownObj));
            $lowestDrawdown = [$lowestKey => $lowestDrawdownObj[$lowestKey]];
            $lowestDrawdownItemId = Arr::first($lowestDrawdown);
            $lowestDrawdownKey = key($lowestDrawdown);
            $priorityKey = max(array_keys($priority));
            $priority = $priority[$priorityKey];
            $appliedPurchaseType = null;

            foreach ($pair as $pairItemCalcHandler) {
                $orderAmount = floor($lowestDrawdownKey / $baseStopLoss);
                $sl = $baseStopLoss;
                $tp = $sl - 1;

                if ($pairItemCalcHandler['id'] !== $lowestDrawdownItemId) {
                    $tp = $sl - 2;
                    $sl = $tp + 3;
                }

                if (empty($appliedPurchaseType)) {
                    $purchaseType = ($pairItemCalcHandler['id'] === $priority)? 'buy' : 'sell';
                    $pairData[$pairItemCalcHandler['id']]['purchase_type'] = $purchaseType;
                    $appliedPurchaseType = $purchaseType;
                } else {
                    $pairData[$pairItemCalcHandler['id']]['purchase_type'] = ($appliedPurchaseType === 'buy')? 'sell' : 'buy';
                }

                $pairData[$pairItemCalcHandler['id']]['order_amount'] = $orderAmount;
                $pairData[$pairItemCalcHandler['id']]['tp'] = $tp;
                $pairData[$pairItemCalcHandler['id']]['sl'] = $sl;
            }

            $tradeController = new TradeController();
            $tradeController->initiateTradeV2(new Request([
                'data' => $this->sortPairByPurchaseType($pairData)
            ]));
        }

        return response()->json(['pair2' => $this->accountId]);
    }

    private function sortPairByPurchaseType($pair)
    {
        uasort($pair, function ($a, $b) {
            if ($a['purchase_type'] === 'buy' && $b['purchase_type'] !== 'buy') {
                return -1; // $a should come first
            } elseif ($a['purchase_type'] !== 'buy' && $b['purchase_type'] === 'buy') {
                return 1; // $b should come first
            }
            return 0; // No change in order
        });

        return $pair;
    }

    // Main function to find pairs
    public function findBestPairs()
    {
        $pairs = [];

        foreach ($this->items as $item) {
            // Skip if this item has already been paired
            if (in_array($item['id'], $this->pairedIds)) {
                continue;
            }

            // Filter potential pairs based on strict criteria
            $potentialPairs = $this->items->filter(function ($other) use ($item) {
                return !$this->isAlreadyPaired($other['id']) && $this->meetsStrictCriteria($item, $other);
            });

            // Rank all potential pairs based on weighted criteria
            $bestPair = $potentialPairs->sortByDesc(function ($other) use ($item) {
                return $this->calculateWeightedScore($item, $other);
            })->first();

            if ($bestPair) {
                $pairs[] = ['item' => $item, 'pair' => $bestPair];
                $this->pairedIds[] = $item['id'];
                $this->pairedIds[] = $bestPair['id'];
            }
        }

        return $pairs;
    }

    // Check if an item is already paired
    protected function isAlreadyPaired($id)
    {
        return in_array($id, $this->pairedIds);
    }

    // Strict criteria
    protected function meetsStrictCriteria($item, $other)
    {
        if ($item['id'] === $other['id']) {
            return false;
        }

        if ($item['n_trades'] >= 5 || $other['n_trades'] >= 5) {
            return false;
        }

        if ($item['trading_account_credential']['user_account_id'] === $other['trading_account_credential']['user_account_id']) {
            return false;
        }

        if ($item['trading_account_credential']['user_account']['trading_unit']['unit_id'] === $other['trading_account_credential']['user_account']['trading_unit']['unit_id']) {
            return false;
        }

        // Closest equity value within brackets
        if (!$this->sameEquityBracket($item['latest_equity'], $other['latest_equity'])) {
            return false;
        }

        return true;
    }

    // Weighted score calculation
    protected function calculateWeightedScore($item, $other)
    {
        $score = 0;

        // Positive calculation of equity difference (weight = 5)
        $itemEquityDiff = $item['latest_equity'] - $item['starting_daily_equity'];
        $otherEquityDiff = $other['latest_equity'] - $other['starting_daily_equity'];
        if ($itemEquityDiff > 0 && $otherEquityDiff > 0) {
            $score += 5;
        }


        // Proximity of equity values (weight = 6 for closer equity)
        $equityDifference = abs($item['latest_equity'] - $other['latest_equity']);
        $score += (100000 - $equityDifference) / 1000; // Normalize larger differences to lower scores

        // Different funder alias (weight = 3)
        if ($item['trading_account_credential']['funder']['alias'] !== $other['trading_account_credential']['funder']['alias']) {
            $score += 3;
        }

        return $score;
    }

    // Equity bracket matching
    protected function sameEquityBracket($itemEquity, $otherEquity)
    {
        $brackets = [
            [20000, 59000],
            [60000, 89000],
            [90000, 150000],
        ];

        foreach ($brackets as [$min, $max]) {
            if ($itemEquity >= $min && $itemEquity <= $max && $otherEquity >= $min && $otherEquity <= $max) {
                return true;
            }
        }

        return false;
    }






    private function getPairableAccounts()
    {
        $items = TradeReport::with([
                'TradingAccountCredential',
                'TradingAccountCredential.funder',
                'TradingAccountCredential.userAccount.tradingUnit',
                'TradingAccountCredential.userAccount.funderAccountCredential',
                'TradingAccountCredential.historyV3'
            ])
            ->whereHas('tradingAccountCredential', function($query) {
                $query->where('status', 'active')
                ->where('funder_account_id', 'NOT LIKE', 'DEMO%');
            })
            ->where('account_id', $this->accountId)
            ->where('status', 'idle')
            ->where('n_trades', '<', 5)
            ->get();

        return $items->toArray();
    }
}
