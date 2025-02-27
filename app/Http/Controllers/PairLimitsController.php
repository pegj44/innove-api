<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PairLimitsController extends Controller
{
    public $items;

    public function __construct($items)
    {
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
        $minSl = 65;
        $maxSl = 75;

        $finalTicks = rand($minSl, $maxSl);
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

        foreach ($items as $item) {

            $rdd = TradeController::getCalculatedRdd($item);
            $limitAmounts = [];

            $limitAmounts['sl'][] = $item['trading_account_credential']['package']['per_trade_drawdown'];
            $limitAmounts['sl'][] = $item['trading_account_credential']['package']['daily_drawdown'];
            $limitAmounts['sl'][] = getRemainingDailyStopLoss($item);

            if (is_numeric($rdd)) {
                $limitAmounts['sl'][] = TradeController::getCalculatedRdd($item);
            }

            $limitAmounts['tp'][] = $item['trading_account_credential']['package']['per_trade_target_profit'];
            $limitAmounts['tp'][] = $item['trading_account_credential']['package']['daily_target_profit'];
            $limitAmounts['tp'][] = getRemainingDailyTargetProfit($item);
            $limitAmounts['tp'][] = getRemainingTargetProfit($item) + 20;

            $tp = (float) min($limitAmounts['tp']);
            $sl = (float) min($limitAmounts['sl']);

            $limits[$item['id']] = [
                'id' => $item['id'],
                'tp' => $tp,
                'sl' => $sl,
                'starting_bal' => (float) $item['trading_account_credential']['package']['starting_balance'],
                'phase' => $item['trading_account_credential']['package']['current_phase']
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
        $minLots = 1.3;
        $maxLots = 1.5;

        if ($equity > 50000) {
            $minLots = 2.3;
            $maxLots = 2.5;
        }

        if (!$lots) {
            $lots = $this->randomFloat($minLots, $maxLots);
        }
        $ticks = floor($amount / $lots);

        return [
            'ticks' => $ticks,
            'lots' => $lots,
            'amount' => $ticks * $lots
        ];
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
                'phase' => $pairLimits[0]['phase']
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
                'phase' => $pairLimits[1]['phase']
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
                'phase' => $pairLimits[0]['phase']
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
                'phase' => $pairLimits[1]['phase']
            ];
        }

        $limits = $this->equalizeTpSL($itemIds, $pairLimits, $limits);
        $limits = $this->convertForexTpSl($itemIds, $pairLimits, $limits);
        $limits = $this->scaleDownFunderPro($itemIds, $pairLimits, $limits);

        return $limits;
    }

    public function scaleDownFunderPro($itemIds, $pairLimits, $limits)
    {
        $funders = [
            $itemIds[0]['trading_account_credential']['package']['funder']['alias'],
            $itemIds[1]['trading_account_credential']['package']['funder']['alias']
        ];

        $hasFpro = $this->hasSpecificCrossFunder($funders, 'fpro');

        if ($hasFpro === false) {
            return $limits;
        }

        $fproKey = $hasFpro;
        $nonFproKey = ($fproKey === 0)? 1 : 0;

        $nonFproLots = $limits[$itemIds[$nonFproKey]['id']]['tp']['lots'];
        $nonFproTp = $limits[$itemIds[$nonFproKey]['id']]['tp']['ticks'];
        $nonFproSL = $limits[$itemIds[$nonFproKey]['id']]['sl']['ticks'];

        $fproLots = $nonFproLots;

        if ($itemIds[0]['trading_account_credential']['package']['current_phase'] !== 'phase-3') {
            $fproLots = $nonFproLots / 2;
        }

        $fproLots = $fproLots / 10;

        $limits[$itemIds[$fproKey]['id']]['tp']['lots'] = $fproLots;
        $limits[$itemIds[$fproKey]['id']]['tp']['ticks'] = $nonFproTp * 10;
        $limits[$itemIds[$fproKey]['id']]['tp']['amount'] = ($nonFproTp * 10) * $fproLots;

        $limits[$itemIds[$fproKey]['id']]['sl']['lots'] = $fproLots;
        $limits[$itemIds[$fproKey]['id']]['sl']['ticks'] = $nonFproSL * 10;
        $limits[$itemIds[$fproKey]['id']]['sl']['amount'] = ($nonFproSL * 10) * $fproLots;

        return $limits;
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

        $limits[$pairLimits[1]['id']] = $limits[$pairLimits[0]['id']];

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

        return $limits;
    }
}
