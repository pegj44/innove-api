<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PairLimitsController extends Controller
{
    public $items;

    public $futuresMinSl = 90;
    public $futuresMaxSl = 100;
    public $forexMinSl;
    public $forexMaxSl;

    public function __construct($items)
    {
        $this->forexMinSl = $this->futuresMinSl * 10;
        $this->forexMaxSl = $this->futuresMaxSl * 10;

        $this->items = is_object($items)? $items->toArray() : $items;
    }

    public function getPossibleTradeCharge($amount = 30)
    {
        if ($amount < 10) {
            return 10;
        }

        if ($amount < 15) {
            return 15;
        }

        if ($amount < 18) {
            return 20;
        }

        if ($amount < 24) {
            return 25;
        }

        if ($amount < 28) {
            return 30;
        }

        if ($amount < 33) {
            return 35;
        }

        return 40;
    }

    public function getBestOrderAmountTicksRatio($targetAmount)
    {
        $targetAmount = (float) $targetAmount;

        $finalTicks = rand($this->futuresMinSl, $this->futuresMaxSl);
        $divide = floor($targetAmount) / $finalTicks;
        $finalOrderAmnt = floor($divide);

        return [
            'ticks' => $finalTicks,
            'lots' => $finalOrderAmnt,
            'amount' => $finalTicks * $finalOrderAmnt,
            'charge' => $this->getPossibleTradeCharge($finalOrderAmnt)
        ];
    }

    public function getMatchingSlTicksDiff($amount, $ticks, $lots, $charge = 0, $diff = 2)
    {
        $projectedAmount = ($ticks + $diff) * $lots;
        $projectedAmountWithCharge = $projectedAmount + $charge;

        if ($amount >= $projectedAmountWithCharge) {
            $projectedTicks = $projectedAmount / $lots;
            $projectedTicks = floor($projectedTicks);
            return [
                'amount' => $projectedTicks * $lots,
                'lots' => $lots,
                'ticks' => $projectedTicks
            ];
        }

        $projectedTicks = ($amount - $charge) / $lots;
        $projectedTicks = floor($projectedTicks);

        return [
            'amount' => $projectedTicks * $lots,
            'lots' => $lots,
            'ticks' => $projectedTicks
        ];
    }

    public function getItemsLowestTpSl($items)
    {
        $limits = [];
        $lowestItem = [];

        $handlerMinTtP = (float) $items[0]['trading_account_credential']['package']['per_trade_target_profit'];
        $handlerMaxTtP = (float) $items[0]['trading_account_credential']['package']['max_per_trade_target_profit'];

        if ($handlerMaxTtP > 0) {
            $handlerRandTtP = rand($handlerMinTtP, $handlerMaxTtP);
        } else {
            $handlerRandTtP = $handlerMinTtP;
        }

        $isCrossPhase = ($items[0]['trading_account_credential']['package']['current_phase'] != $items[1]['trading_account_credential']['package']['current_phase']);

        $prices = $this->getPrices();
        $tradeConfig = TradeConfigController::get();

        foreach ($items as $item) {

            $rdd = TradeController::getCalculatedRdd($item);
            $limitAmounts = [];

            $maxSlP = (float) $item['trading_account_credential']['package']['max_per_trade_drawdown'];

            if ($maxSlP > 0) {
                $limitAmounts['sl'][] = (float) $item['trading_account_credential']['package']['max_per_trade_drawdown'];
            } else {
                $limitAmounts['sl'][] = (float) $item['trading_account_credential']['package']['per_trade_drawdown'];
            }

            $limitAmounts['sl'][] = $item['trading_account_credential']['package']['daily_drawdown'];
            $limitAmounts['sl'][] = getRemainingDailyStopLoss($item);

            $marginCap = CalculationsController::adjustLimitsByMarginCap($item, $prices, $tradeConfig);

            if ($marginCap !== null) {
                $limitAmounts['sl'][] = $marginCap;
                $limitAmounts['tp'][] = $marginCap;
            }

            if (is_numeric($rdd)) {
                $limitAmounts['sl'][] = TradeController::getCalculatedRdd($item);
            }

            if ($isCrossPhase) {
                $maxTtP = (float) $item['trading_account_credential']['package']['max_per_trade_target_profit'];
                if ($maxTtP > 0) {
                    $minTtP = (float) $item['trading_account_credential']['package']['per_trade_target_profit'];
                    $limitAmounts['tp'][] = rand($minTtP, $maxTtP);
                } else {
                    $limitAmounts['tp'][] = $item['trading_account_credential']['package']['per_trade_target_profit'];
                }
            } else {
                $limitAmounts['tp'][] = $handlerRandTtP;
            }


            $limitAmounts['tp'][] = $item['trading_account_credential']['package']['daily_target_profit'];
            $remainingDailyTp = getRemainingDailyTargetProfit($item);

            $limitAmounts['tp'][] = $remainingDailyTp + 100;
            $remainingTp = getRemainingTargetProfit($item);
            $limitAmounts['tp'][] = $remainingTp + 100;

            $tp = (float) min($limitAmounts['tp']);
            $sl = (float) min($limitAmounts['sl']);

            $limits[$item['id']] = [
                'id' => $item['id'],
                'tp' => $tp,
                'sl' => $sl,
                'starting_bal' => (float) $item['trading_account_credential']['package']['starting_balance'],
                'phase' => $item['trading_account_credential']['package']['current_phase'],
                'funder' => strtolower($item['trading_account_credential']['package']['funder']['alias']),
            ];

            $lowestItems = [$sl, $tp];
            $lowestItem[$item['id']] = min($lowestItems);
        }

        asort($lowestItem);
        $sortedLimits = [];

        foreach ($lowestItem as $itemId => $sortedItem) {
            $sortedLimits[] = $limits[$itemId];
        }

        return $sortedLimits;
    }

    public function randomFloat($min, $max)
    {
        $minScaled = $min * 10; // Scale to integer range (e.g., 1.3 -> 13)
        $maxScaled = $max * 10; // Scale to integer range (e.g., 1.5 -> 15)

        $randomScaled = mt_rand($minScaled, $maxScaled); // Generate random integer between 13 and 15
        return $randomScaled / 10; // Scale back to float with one decimal place
    }

    public function convertUnitsToLots($amount, $equity, $lots = 0)
    {
        $fiftyKMinLots = 1.3;
        $fiftyKMaxLots = 1.5;

        $ticks = rand($this->forexMinSl, $this->forexMaxSl);

        if (!$lots) {
            $lots = (float) $amount / $ticks;
            $lots = floor($lots * 100) / 100;
            $lots = number_format($lots, 2);

            if ($equity <= 50000 && $lots > $fiftyKMaxLots) {
                $lots = $this->randomFloat($fiftyKMinLots, $fiftyKMaxLots);
                $ticks = floor($amount / $lots);
            }

            if ($equity >= 90000 && $lots > 3) {
                $lots = 3;
                $ticks = floor($amount / $lots);
            }

        } else {
            $ticks = (float) $amount * (float) $lots;
        }

        return [
            'ticks' => $ticks,
            'lots' => $lots,
            'amount' => $ticks * $lots
        ];
    }

    public function convertToForexInputs($lots, $ticks, $funder, $symbol = '')
    {
        $forexSymbols = TradeConfigController::get('forexSymbols');
//        $multipliers = TradeConfigController::get('multipliers');

        if (!in_array($symbol, $forexSymbols)) {
            return [
                'lots' => $lots,
                'ticks' => $ticks,
                'amount' => $lots * $ticks,
            ];
        }

//        if (!isset($multipliers[$funder])) {

            $lots = $lots / 10;
            $ticks = $ticks * 10;

            return [
                'lots' => $lots,
                'ticks' => $ticks,
                'amount' => $lots * $ticks,
            ];
//        }
//
//        $lotsMultiplier =  $multipliers[$funder]['lots']['value'];
//        $ticksMultiplier =  $multipliers[$funder]['ticks']['value'];
//
//        if ($multipliers[$funder]['lots']['multiply']) {
//            $lots = $lots * $lotsMultiplier;
//        } else {
//            $lots = $lots / $lotsMultiplier;
//        }
//
//        if ($multipliers[$funder]['ticks']['multiply']) {
//            $ticks = $ticks * $ticksMultiplier;
//            $amount = $lots * $ticks;
//        } else {
//            $ticks = $ticks / $ticksMultiplier;
//            $amount = $lots * ($ticks * 100);
//        }
//
//        return [
//            'lots' => $lots,
//            'ticks' => $ticks,
//            'amount' => $amount,
//        ];
    }

    public function divideLowestRatioLotsByTwo(&$limits, $pairLimits, $ratio): void
    {
        // Find the minimum ratio
        $minRatio = min($ratio);
        $maxRatio = max($ratio);
        $minVal = 0;

        // Get the max trade value of the higher ratio account.
        foreach ($pairLimits as $pairLimit) {
            $ratioKey = $pairLimit['funder'] .'_'. $pairLimit['phase'];
            if ($ratio[$ratioKey] === $maxRatio ) {
                $minVal = min($pairLimit['tp'], $pairLimit['sl']);
            }
        }

        $calcLimit = $this->getBestOrderAmountTicksRatio($minVal);
        $tpHandler = $calcLimit['ticks'];
        $slHandler = $calcLimit['ticks'] + 2; // add 2 ticks of distance between sl and tp

        if (($slHandler * $calcLimit['lots']) > $minVal) {
            $slHandler = $calcLimit['ticks'];
            $tpHandler = $slHandler - 2;
        }

        // Find items with the lowest ratio and divide their lots by 2
        foreach ($limits as $id => &$limitItem) {
            // Get funder and phase from the limit item
            $funder = $limitItem['funder'];
            $phase = $limitItem['phase'];
            $ratioKey = $funder . '_' . $phase;

            // Skip if ratio doesn't exist for this funder_phase combination
            if (!isset($ratio[$ratioKey])) {
                continue;
            }

            // If this item has the lowest ratio, divide lots by 2
            if ($ratio[$ratioKey] === $minRatio) {
                $lots = ($calcLimit['lots'] / 2);

//                $limitItem['tp'] = $this->convertToForexInputs($lots, $tpHandler, $limitItem['funder'], $limitItem['symbol']);
//                $limitItem['sl'] = $this->convertToForexInputs($lots, $slHandler, $limitItem['funder'], $limitItem['symbol']);
            } else {
                $lots = $calcLimit['lots'];

//                $limitItem['tp']['lots'] = $lots;
//                $limitItem['tp']['ticks'] = $tpHandler;
//                $limitItem['tp']['amount'] = $tpHandler * $lots;
//
//                $limitItem['sl']['lots'] = $lots;
//                $limitItem['sl']['ticks'] = $slHandler;
//                $limitItem['sl']['amount'] = $slHandler * $lots;
            }

            $limitItem['tp']['lots'] = $lots;
            $limitItem['tp']['ticks'] = $tpHandler;
            $limitItem['tp']['amount'] = $tpHandler * $lots;

            $limitItem['sl']['lots'] = $lots;
            $limitItem['sl']['ticks'] = $slHandler;
            $limitItem['sl']['amount'] = $slHandler * $lots;
        }
    }

    public function applyFunderRatio($itemIds, $pairLimits, &$limits)
    {
        $ratio = $this->getFunderRatio($itemIds);

        if (!$ratio) {
            return $limits;
        }

        // Find the minimum ratio to use as baseline
        $minRatio = min($ratio);

        // Process each item in limits
        foreach ($limits as $id => &$limitItem) {
            // Get funder and phase from the limit item
            $funder = $limitItem['funder'];
            $phase = $limitItem['phase'];
            $ratioKey = $funder . '_' . $phase;

            // Skip if ratio doesn't exist for this funder_phase combination
            if (!isset($ratio[$ratioKey])) {
                continue;
            }

            // Get the ratio for this item
            $currentRatio = $ratio[$ratioKey];

            // Calculate the multiplier (ratio relative to minimum ratio)
            $multiplier = $currentRatio / $minRatio;

            // Find corresponding pairLimits item for validation
            $pairLimitItem = null;
            foreach ($pairLimits as $pairItem) {
                if ($pairItem['funder'] === $funder && $pairItem['phase'] === $phase) {
                    $pairLimitItem = $pairItem;
                    break;
                }
            }

            // Calculate new values for both tp and sl
            $newTpLots = isset($limitItem['tp']) ? $limitItem['tp']['lots'] * $multiplier : 0;
            $newTpAmount = isset($limitItem['tp']) ? $newTpLots * $limitItem['tp']['ticks'] : 0;
            $newSlLots = isset($limitItem['sl']) ? $limitItem['sl']['lots'] * $multiplier : 0;
            $newSlAmount = isset($limitItem['sl']) ? $newSlLots * $limitItem['sl']['ticks'] : 0;

            // Validation for higher ratio funders: check both tp and sl amounts
            if ($currentRatio > $minRatio && $pairLimitItem !== null) {
                $minTpSlLimit = min($pairLimitItem['tp'], $pairLimitItem['sl']);

                // Check if either tp or sl amount exceeds the limit
                $tpExceedsLimit = isset($limitItem['tp']) && $newTpAmount > $minTpSlLimit;
                $slExceedsLimit = isset($limitItem['sl']) && $newSlAmount > $minTpSlLimit;

                if ($tpExceedsLimit || $slExceedsLimit) {
                    // Validation failed - divide lowest ratio item's tp and sl lots by 2
                    $this->divideLowestRatioLotsByTwo($limits, $pairLimits, $ratio);
                } else {

                    // Validation passed - update both tp and sl values
                    if (isset($limitItem['tp'])) {
                        $limitItem['tp']['lots'] = $newTpLots;
                        $limitItem['tp']['amount'] = $newTpAmount;
                    }
                    if (isset($limitItem['sl'])) {
                        $limitItem['sl']['lots'] = $newSlLots;
                        $limitItem['sl']['amount'] = $newSlAmount;
                    }
                }
            }
        }

        return $limits;
    }

    public function calculateFproCrossPhaseLimits($pairLimits)
    {
        $stopLossAllowance = 25;
        $phase2MinAmnt = 0;
        $phase3MinAmnt = 0;
        $phase2Id = 0;
        $phase3Id = 0;
        $equities = [];

        foreach ($pairLimits as $limit) {
            $stopLoss = (float) $limit['sl'];
            $equities[] = $limit['starting_bal'];

            if ($limit['phase'] === 'phase-2') {

                $phase2Id = $limit['id'];
                $phase2MinAmnt = min($limit['tp'], $stopLoss);
            }

            if ($limit['phase'] === 'phase-3') {
                $phase3Id = $limit['id'];
                $phase3MinAmnt = min($limit['tp'], $stopLoss);
            }
        }

        /**
         * Phase-3 vs Phase2 Ratio
         */
        if ($phase2Id && $phase3Id) {
            $maxNum = $phase3MinAmnt * 4;
            if ($maxNum > $phase2MinAmnt) {
                $maxNum = $phase2MinAmnt;
            }

            $baseLimits = $this->getBestOrderAmountTicksRatio($maxNum);
            $baseLimits = $this->convertUnitsToLots($baseLimits['amount'], min($equities));

            $slTicks = (float) $baseLimits['ticks'];
            $tpTicks = $slTicks - 20;
            $lots = (float) $baseLimits['lots'];
            $phase3Lots = $lots / 4;

            $phase3Lots = floor($phase3Lots * 100) / 100;
            $phase3Lots = number_format($phase3Lots, 2);

            return [
                $phase2Id => [
                    'tp' => [
                        'ticks' => $tpTicks,
                        'lots' => $lots,
                        'amount' => $tpTicks * $lots
                    ],
                    'sl' => [
                        'ticks' => $slTicks,
                        'lots' => $lots,
                        'amount' => $slTicks * $lots
                    ],
                    'phase' => 'phase-2'
                ],
                $phase3Id => [
                    'tp' => [
                        'ticks' => $tpTicks,
                        'lots' => $phase3Lots,
                        'amount' => $tpTicks * $phase3Lots
                    ],
                    'sl' => [
                        'ticks' => $slTicks,
                        'lots' => $phase3Lots,
                        'amount' => $slTicks * $phase3Lots
                    ],
                    'phase' => 'phase-3'
                ]
            ];
        }

        return [];
    }

    public function getPrices()
    {
        $currentPrices = [];

        foreach ($this->items as $item) {
            $symbol = strtolower($item['trading_account_credential']['package']['symbol']);
            if (isset($currentPrices[$symbol])) {
                continue;
            }
            $currentPrices[$symbol] = PriceFeedController::getPrice($symbol);
        }

        return $currentPrices;
    }

    public function getLimits()
    {
        $pairLimits = $this->getItemsLowestTpSl($this->items);

        if (($this->items[0]['trading_account_credential']['package']['funder']['alias'] === 'FPRO' &&
            $this->items[1]['trading_account_credential']['package']['funder']['alias'] === 'FPRO') &&
            $this->items[0]['trading_account_credential']['package']['current_phase'] !== $this->items[1]['trading_account_credential']['package']['current_phase']) {
            return $this->calculateFproCrossPhaseLimits($pairLimits);
        }

        $itemIds = [];

        foreach ($this->items as $pItem) {
            if ($pairLimits[0]['id'] == $pItem['id']) {
                $itemIds[0] = $pItem;
            }

            if ($pairLimits[1]['id'] == $pItem['id']) {
                $itemIds[1] = $pItem;
            }
        }

        $tp = $pairLimits[0]['tp'];
        $sl = $pairLimits[0]['sl'];

        $limits = [];

        if ($tp > $sl) { // SL based
            $calcSl = $this->getBestOrderAmountTicksRatio($sl);

            $limits[$pairLimits[0]['id']] = [
                'tp' => [
                    'ticks' => $calcSl['ticks'] - 1,
                    'lots' => $calcSl['lots'],
                    'amount' => ($calcSl['ticks'] - 1) * $calcSl['lots']
                ],
                'sl' => $calcSl,
                'phase' => $pairLimits[0]['phase'],
                'funder' => $pairLimits[0]['funder'],
                'starting_balance' => $itemIds[0]['trading_account_credential']['package']['starting_balance'],
                'symbol' => strtolower($itemIds[0]['trading_account_credential']['package']['symbol']),
            ];

            $projectedSl2 = ($calcSl['ticks'] + 1) * $calcSl['lots'] + $calcSl['charge'];

            $limits[$pairLimits[1]['id']] = [
                'tp' => [
                    'ticks' => $calcSl['ticks'] - 2,
                    'lots' => $calcSl['lots'],
                    'amount' => ($calcSl['ticks'] - 2) * $calcSl['lots']
                ],
                'sl' => [
                    'ticks' => ($pairLimits[1]['sl'] >= $projectedSl2)? $calcSl['ticks'] + 1 : $calcSl['ticks'],
                    'lots' => $calcSl['lots'],
                    'amount' =>  ($pairLimits[1]['sl'] >= $projectedSl2)? ($calcSl['ticks'] + 1) * $calcSl['lots'] : $calcSl['ticks'] * $calcSl['lots']
                ],
                'phase' => $pairLimits[1]['phase'],
                'funder' => $pairLimits[1]['funder'],
                'starting_balance' => $itemIds[1]['trading_account_credential']['package']['starting_balance'],
                'symbol' => strtolower($itemIds[1]['trading_account_credential']['package']['symbol']),
            ];

        } else { // TP based
            $calcTp = $this->getBestOrderAmountTicksRatio($tp);

            $limits[$pairLimits[0]['id']] = [
                'tp' => $calcTp,
                'sl' => [
                    'ticks' => $calcTp['ticks'] + 1,
                    'lots' => $calcTp['lots'],
                    'amount' => ($calcTp['ticks'] + 1) * $calcTp['lots']
                ],
                'phase' => $pairLimits[0]['phase'],
                'funder' => $pairLimits[0]['funder'],
                'starting_balance' => $itemIds[0]['trading_account_credential']['package']['starting_balance'],
                'symbol' => strtolower($itemIds[0]['trading_account_credential']['package']['symbol']),
            ];

            $projectedSl2 = ($calcTp['ticks'] + 2) * $calcTp['lots'] + $calcTp['charge'];

            $limits[$pairLimits[1]['id']] = [
                'tp' => [
                    'ticks' => $calcTp['ticks'] - 1,
                    'lots' => $calcTp['lots'],
                    'amount' => ($calcTp['ticks'] - 1) * $calcTp['lots']
                ],
                'sl' => [
                    'ticks' => ($pairLimits[1]['sl'] >= $projectedSl2)? $calcTp['ticks'] + 2 : $calcTp['ticks'],
                    'lots' => $calcTp['lots'],
                    'amount' =>  ($pairLimits[1]['sl'] >= $projectedSl2)? ($calcTp['ticks'] + 2) * $calcTp['lots'] : $calcTp['ticks'] * $calcTp['lots']
                ],
                'phase' => $pairLimits[1]['phase'],
                'funder' => $pairLimits[1]['funder'],
                'starting_balance' => $itemIds[1]['trading_account_credential']['package']['starting_balance'],
                'symbol' => strtolower($itemIds[1]['trading_account_credential']['package']['symbol']),
            ];
        }

        $limits = $this->equalizeTpSL($itemIds, $pairLimits, $limits);
//        $limits = $this->convertForexTpSl($itemIds, $pairLimits, $limits);
        $limits = $this->applyFunderRatio($itemIds, $pairLimits, $limits);
        $limits = $this->applyForexMultipliers($limits);

        return $limits;
    }

    /**
     * Fix PipFarm amount in dollars value.
     *
     * @param $limits
     * @return array
     */
    public function applyForexMultipliers($limits)
    {
        $multipliers = TradeConfigController::get('multipliers');

        foreach ($limits as $id => $limitItem) {

            $funder = $limitItem['funder'];
            $lots = $limitItem['tp']['lots'];

            if (isset($multipliers[$funder])) {

                $lotsMultiplier =  $multipliers[$funder]['lots']['value'];
                $ticksMultiplier =  $multipliers[$funder]['ticks']['value'];

                if ($multipliers[$funder]['lots']['multiply']) {
                    $lots = $lots * $lotsMultiplier;
                } else {
                    $lots = $lots / $lotsMultiplier;
                }

                if ($multipliers[$funder]['ticks']['multiply']) {
                    $tpTicks = $limitItem['tp']['ticks'] * $ticksMultiplier;
                    $tpAmount = $lots * $tpTicks;

                    $slTicks = $limitItem['sl']['ticks'] * $ticksMultiplier;
                    $slAmount = $lots * $slTicks;

                } else {
//                    $ticks = $ticks / $ticksMultiplier;
//                    $amount = $lots * ($ticks * 100);

                    $tpTicks = $limitItem['tp']['ticks'] / $ticksMultiplier;
                    $tpAmount = $lots * ($tpTicks * 100);

                    $slTicks = $limitItem['sl']['ticks'] / $ticksMultiplier;
                    $slAmount = $lots * ($slTicks * 100);

                }

                $limits[$id]['tp']['lots'] = $lots;
                $limits[$id]['tp']['ticks'] = $tpTicks;
                $limits[$id]['tp']['amount'] = $tpAmount;

                $limits[$id]['sl']['lots'] = $lots;
                $limits[$id]['sl']['ticks'] = $slTicks;
                $limits[$id]['sl']['amount'] = $slAmount;
            }
        }

        return $limits;
    }

    public function getFunderRatio($itemIds)
    {
        $ratio = TradeConfigController::get('ratio');

        $pckg_1 = $itemIds[0]['trading_account_credential']['package'];
        $pckg_2 = $itemIds[1]['trading_account_credential']['package'];
        $fndr_1 = strtolower($pckg_1['funder']['alias']);
        $fndr_2 = strtolower($pckg_2['funder']['alias']);

        foreach ($ratio as $key => $val) {
            $search1 = $fndr_1 .'_'. $pckg_1['current_phase']. '_'. $fndr_2 .'_'. $pckg_2['current_phase'];
            $search2 = $fndr_2 .'_'. $pckg_2['current_phase']. '_'. $fndr_1 .'_'. $pckg_1['current_phase'];

            $val = explode(':', $val);

            if ($key === $search1) {
                $funderRatio = [
                    $fndr_1 .'_'. $pckg_1['current_phase'] => (int) $val[0],
                    $fndr_2 .'_'. $pckg_2['current_phase'] => (int) $val[1]
                ];

                asort($funderRatio);
                return $funderRatio;
            }

            if ($key === $search2) {

                $funderRatio = [
                    $fndr_1 .'_'. $pckg_1['current_phase'] => (int) $val[1],
                    $fndr_2 .'_'. $pckg_2['current_phase'] => (int) $val[0]
                ];

                asort($funderRatio);
                return $funderRatio;
            }
        }

        return false;
    }

    public function hasSpecificCrossFunder($funders, $funder)
    {
        $funderKey = null;
        $hasNonFunderX = false;

        foreach ($funders as $index => $item) {
            if (strtolower($item) !== strtolower($funder)) {
                $hasNonFunderX = true;
                continue;
            }

            $funderKey = $index;
        }

        return ($funderKey !== null && $hasNonFunderX)? $funderKey : false;
    }

    /**
     * Equalize TP and SL if cross funder.
     *
     * @param $itemIds
     * @param $pairLimits
     * @param $limits
     * @return mixed
     */
    public function equalizeTpSL($itemIds, $pairLimits, $limits)
    {
        if ($itemIds[0]['trading_account_credential']['package']['funder']['alias'] === $itemIds[1]['trading_account_credential']['package']['funder']['alias']) {
            return $limits;
        }

        $tpTicks = $limits[$pairLimits[0]['id']]['tp']['ticks'];
        $limits[$pairLimits[0]['id']]['sl']['ticks'] = $tpTicks + 2;
        $limits[$pairLimits[0]['id']]['sl']['amount'] = $limits[$pairLimits[0]['id']]['sl']['ticks'] * $limits[$pairLimits[0]['id']]['sl']['lots'];

        $limits[$pairLimits[1]['id']]['tp'] = $limits[$pairLimits[0]['id']]['tp'];
        $limits[$pairLimits[1]['id']]['sl'] = $limits[$pairLimits[0]['id']]['sl'];

        return $limits;
    }

    public function convertForexTpSl($itemIds, $pairLimits, $limits)
    {
        $generatedLots = 0;

        $startingBal1 = (float) $itemIds[0]['trading_account_credential']['package']['starting_balance'];
        $startingBal2 = (float) $itemIds[1]['trading_account_credential']['package']['starting_balance'];

        $lotsEquityBracket = $startingBal1;

        if ($startingBal1 != $startingBal2) {
            $lotsEquityBracket = min([$startingBal1, $startingBal2]);
        }

        if ($itemIds[0]['trading_account_credential']['package']['asset_type'] === 'forex') {
            $newTp = $this->convertUnitsToLots($limits[$pairLimits[0]['id']]['tp']['amount'], $lotsEquityBracket);
            $generatedLots = $newTp['lots'];

            if ($itemIds[1]['trading_account_credential']['package']['asset_type'] === 'futures') {

                $limits[$pairLimits[0]['id']]['tp']['lots'] = (float) $limits[$pairLimits[0]['id']]['tp']['amount'] / ($limits[$pairLimits[0]['id']]['tp']['ticks'] * 10);
                $limits[$pairLimits[0]['id']]['tp']['ticks'] = (float) $limits[$pairLimits[0]['id']]['tp']['ticks'] * 10;

                $limits[$pairLimits[0]['id']]['sl']['lots'] = $limits[$pairLimits[0]['id']]['tp']['lots'];
                $limits[$pairLimits[0]['id']]['sl']['ticks'] = (float) $limits[$pairLimits[0]['id']]['sl']['ticks'] * 10;
            } else {
                $limits[$pairLimits[0]['id']]['tp'] = $newTp;
                $limits[$pairLimits[0]['id']]['sl'] = $this->convertUnitsToLots($limits[$pairLimits[0]['id']]['sl']['amount'], $lotsEquityBracket, $generatedLots);
            }
        }

        if ($itemIds[1]['trading_account_credential']['package']['asset_type'] === 'forex') {

            $newTp = $this->convertUnitsToLots($limits[$pairLimits[1]['id']]['tp']['amount'], $lotsEquityBracket, $generatedLots);
            $generatedLots = ($generatedLots)? $generatedLots : $newTp['lots'];

            if ($itemIds[0]['trading_account_credential']['package']['asset_type'] === 'futures') {

                $limits[$pairLimits[1]['id']]['tp']['lots'] = (float) $limits[$pairLimits[1]['id']]['tp']['amount'] / ($limits[$pairLimits[1]['id']]['tp']['ticks'] * 10);
                $limits[$pairLimits[1]['id']]['tp']['ticks'] = (float) $limits[$pairLimits[1]['id']]['tp']['ticks'] * 10;

                $limits[$pairLimits[1]['id']]['sl']['lots'] = $limits[$pairLimits[1]['id']]['tp']['lots'];
                $limits[$pairLimits[1]['id']]['sl']['ticks'] = (float) $limits[$pairLimits[1]['id']]['sl']['ticks'] * 10;
            } else {
                $limits[$pairLimits[1]['id']]['tp'] = $newTp;
                $limits[$pairLimits[1]['id']]['sl'] = $this->convertUnitsToLots($limits[$pairLimits[1]['id']]['sl']['amount'], $lotsEquityBracket, $generatedLots);
            }
        }

        if ($itemIds[0]['trading_account_credential']['package']['asset_type'] === 'forex' &&
            $itemIds[1]['trading_account_credential']['package']['asset_type'] === 'forex') {

            $lots = $limits[$pairLimits[1]['id']]['tp']['lots'];
            $item1Tp = $limits[$pairLimits[0]['id']]['tp']['ticks'];
            $item1Sl = $item1Tp + 30;

            $item2Tp = $item1Tp + 10;
            $item2Sl = $item1Tp + 20;

            $limits[$pairLimits[0]['id']]['tp']['ticks'] = $item1Tp;
            $limits[$pairLimits[0]['id']]['tp']['amount'] = $item1Tp * $lots;
            $limits[$pairLimits[0]['id']]['sl']['ticks'] = $item1Sl;
            $limits[$pairLimits[0]['id']]['sl']['amount'] = $item1Sl * $lots;

            $limits[$pairLimits[1]['id']]['tp']['ticks'] = $item2Tp;
            $limits[$pairLimits[1]['id']]['tp']['amount'] = $item2Tp * $lots;
            $limits[$pairLimits[1]['id']]['sl']['ticks'] = $item2Sl;
            $limits[$pairLimits[1]['id']]['sl']['amount'] = $item2Sl * $lots;
        }

//        foreach ($limits as $itemId => $limitItem) {
//            if ($limitItem['funder'] === 'pipf' || $limitItem['funder'] === 'fnxt') {
//                $newTpTick = (float) $limitItem['tp']['ticks'] / 100;
//                $newTpTick = floor($newTpTick * 100) / 100;
//
//                $newSlTick = (float) $limitItem['sl']['ticks'] / 100;
//                $newSlTick = floor($newSlTick * 100) / 100;
//
//                $limits[$itemId]['tp']['ticks'] = number_format($newTpTick, 2, '.', '');
//                $limits[$itemId]['sl']['ticks'] = number_format($newSlTick, 2, '.', '');
//            }
//        }

        return $limits;
    }
}
