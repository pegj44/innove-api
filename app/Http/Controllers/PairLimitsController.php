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

//        $randPerTradeTpSl = 0;

//        foreach ($items as $handlerItem) {
//            $handlerMinSlP = (float) $items[0]['trading_account_credential']['package']['per_trade_drawdown'];
//            $handlerMaxSlP = (float) $items[0]['trading_account_credential']['package']['max_per_trade_drawdown'];
//            $handlerRandSlP = rand($handlerMinSlP, $handlerMaxSlP);

            $handlerMinTtP = (float) $items[0]['trading_account_credential']['package']['per_trade_target_profit'];
            $handlerMaxTtP = (float) $items[0]['trading_account_credential']['package']['max_per_trade_target_profit'];

            if ($handlerMaxTtP > 0) {
                $handlerRandTtP = rand($handlerMinTtP, $handlerMaxTtP);
            } else {
                $handlerRandTtP = $handlerMinTtP;
            }
//        }
        $isCrossPhase = ($items[0]['trading_account_credential']['package']['current_phase'] != $items[1]['trading_account_credential']['package']['current_phase']);

        foreach ($items as $item) {

            $rdd = TradeController::getCalculatedRdd($item);
            $limitAmounts = [];

//            $minSlP = (float) $item['trading_account_credential']['package']['per_trade_drawdown'];
            $maxSlP = (float) $item['trading_account_credential']['package']['max_per_trade_drawdown'];

            if ($maxSlP > 0) {
                $limitAmounts['sl'][] = (float) $item['trading_account_credential']['package']['max_per_trade_drawdown'];
            } else {
                $limitAmounts['sl'][] = (float) $item['trading_account_credential']['package']['per_trade_drawdown'];
            }


//            $limitAmounts['sl'][] = $handlerRandTtP;
//            $minSlP = (float) $item['trading_account_credential']['package']['per_trade_drawdown'];
//            $maxSlP = (float) $item['trading_account_credential']['package']['max_per_trade_drawdown'];
//
//            if ($maxSlP > 0) {
//                if (!$randPerTradeTpSl) {
//                    $initRandPerTradeTpSl = rand($minSlP, $maxSlP);
//                    $randPerTradeTpSl = $initRandPerTradeTpSl;
//                    $limitAmounts['sl'][] = $initRandPerTradeTpSl;
//                } else {
//                    $limitAmounts['sl'][] = $randPerTradeTpSl;
//                }
//            } else {
//                $limitAmounts['sl'][] = $minSlP;
//            }




//            $perTradeDds[] = $minSlP;
//
//            if ($maxSlP > 0) {
//                $perTradeDds[] = $maxSlP;
//            }


//            $minSlP = (float) $item['trading_account_credential']['package']['per_trade_drawdown'];
//            $maxSlP = (float) $item['trading_account_credential']['package']['max_per_trade_drawdown'];
//
//            if ($maxSlP > 0) {
//                $limitAmounts['sl'][] = rand($minSlP, $maxSlP);
//            } else {
//                $limitAmounts['sl'][] = $minSlP;
//            }

            $limitAmounts['sl'][] = $item['trading_account_credential']['package']['daily_drawdown'];
            $limitAmounts['sl'][] = getRemainingDailyStopLoss($item);

            if (is_numeric($rdd)) {
                $limitAmounts['sl'][] = TradeController::getCalculatedRdd($item);
            }

//            $minTtP = (float) $item['trading_account_credential']['package']['per_trade_target_profit'];
//            $maxTtP = (float) $item['trading_account_credential']['package']['max_per_trade_target_profit'];
//
//            $perTradeTPs[] = $minTtP;
//
//            if ($maxTtP > 0) {
//                $perTradeTPs[] = $maxTtP;
//            }

//            $minTtP = (float) $item['trading_account_credential']['package']['per_trade_target_profit'];
//            $maxTtP = (float) $item['trading_account_credential']['package']['max_per_trade_target_profit'];
//
//            if ($maxTtP > 0) {
//                $limitAmounts['tp'][] = rand($minTtP, $maxTtP);
//            } else {
//                $limitAmounts['tp'][] = $minTtP;
//            }

//            if ($maxSlP > 0) {
//                if (!$randPerTradeTpSl) {
//                    $initRandPerTradeTpSl = rand($minSlP, $maxSlP);
//                    $randPerTradeTpSl = $initRandPerTradeTpSl;
//                    $limitAmounts['tp'][] = $initRandPerTradeTpSl;
//                } else {
//                    $limitAmounts['tp'][] = $randPerTradeTpSl;
//                }
//            } else {
//                $limitAmounts['tp'][] = $minSlP;
//            }

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

//            info(print_r([
//                '$remainingDailyTp' => $remainingDailyTp,
//                '$remainingTp' => $remainingTp
//            ], true));

//            $remainingTps[] = $remainingTp;

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

//        info(print_r([
//            '$sortedLimits' => $sortedLimits
//        ], true));

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

    public function scaleDownFunderPro($itemIds, $pairLimits, $limits)
    {
        $ratio = $this->getFunderRatio($itemIds);

        if (!$ratio) {
            return $limits;
        }

        $minLimits = [];
        foreach ($pairLimits as $item) {
            $key = $item['funder'] . "_" . $item['phase'];
            $minValue = min($item['tp'], $item['sl']);
            $minLimits[$key] = $minValue;
        }

        $ratioOperator = [];
        $keys = array_keys($minLimits);

        if (count($keys) == 2) {
            $key1 = $keys[0]; // e.g., fpro_phase-2
            $key2 = $keys[1]; // e.g., gff_phase-2

            $ratio1 = $ratio[$key1];
            $ratio2 = $ratio[$key2];

            // Ensuring the pair with the higher ratio stays intact
            if ($ratio1 > $ratio2) {
                $higherRatioKey = $key1;
                $lowerRatioKey = $key2;
            } else {
                $higherRatioKey = $key2;
                $lowerRatioKey = $key1;
            }

            $higherRatioValue = $minLimits[$higherRatioKey];
            $lowerRatioValue = $minLimits[$lowerRatioKey];
            $higherRatio = $ratio[$higherRatioKey];
            $lowerRatio = $ratio[$lowerRatioKey];

            // Compute values to maintain the ratio
            if ($lowerRatioValue * ($higherRatio / $lowerRatio) > $higherRatioValue) {
                $ratioOperator[$higherRatioKey] = "same";
                $ratioOperator[$lowerRatioKey] = "divide";
            } else {
                $ratioOperator[$higherRatioKey] = "same";
                $ratioOperator[$lowerRatioKey] = "multiply";
            }
        }

        foreach ($limits as $id => $limitItem) {
            $limitName = $limitItem['funder'] .'_'. $limitItem['phase'];
            $lots = (float) $limitItem['tp']['lots'];

            if ($ratioOperator[$limitName] === 'divide') {
                $lots = $lots / 2;
            } elseif ($ratioOperator[$limitName] === 'multiply') {
                $lots = $lots * 2;
            }

            $limits[$id]['tp']['lots'] = $lots;
            $limits[$id]['sl']['lots'] = $lots;

            $limits[$id]['tp']['amount'] = $lots * (float) $limits[$id]['tp']['ticks'];
            $limits[$id]['sl']['amount'] = $lots * (float) $limits[$id]['sl']['ticks'];
            
            if ($limitItem['funder'] === 'pipf') {
                $limits[$id]['tp']['amount'] = $limits[$id]['tp']['amount'] * 100;
                $limits[$id]['sl']['amount'] = $limits[$id]['sl']['amount'] * 100;
            }
        }

        return $limits;
    }

//    public function scaleDownFunderPro($itemIds, $pairLimits, $limits)
//    {
//        $ratio = $this->getFunderRatio($itemIds);
//
//        if (!$ratio) {
//            return $limits;
//        }
//
//        $lowestVal = 99999;
//        $endRatio = max($ratio);
//        $baseRatio = 0;
//
////        $pair1Key = $pairLimits[0]['funder'] .'_'. $pairLimits[0]['phase'];
////        $pair2Key = $pairLimits[1]['funder'] .'_'. $pairLimits[1]['phase'];
////
////        $pair1MinVal = (float) min($pairLimits[0]['tp'], $pairLimits[0]['sl']);
////        $pair2MinVal = (float) min($pairLimits[1]['tp'], $pairLimits[1]['sl']);
////
////        if ($ratio[$pair1Key] > 1) {
////            if ($pair1MinVal >= ($pair2MinVal * $endRatio)) {
////                $lowestValPair = [
////                    $pair2Key => (int) $ratio[$pair2Key]
////                ];
////            } else {
////                $lowestValPair = [
////                    $pair1Key => (int) $ratio[$pair1Key]
////                ];
////            }
////        } else {
////            if ($pair2MinVal >= ($pair1MinVal * $endRatio)) {
////                $lowestValPair = [
////                    $pair1Key => (int) $ratio[$pair1Key]
////                ];
////            } else {
////                $lowestValPair = [
////                    $pair2Key => (int) $ratio[$pair2Key]
////                ];
////            }
////        }
////
//
//        $lowestValPair = [];
//
//        foreach ($pairLimits as $item) {
//            $minVal = (float) min($item['tp'], $item['sl']);
//            $ratioName = $item['funder'] .'_'. $item['phase'];
//            $itemRatio = $ratio[$ratioName];
//
//            if ($minVal <= $lowestVal) {
//                if (empty($lowestValPair)) {
//                    $lowestValPair = [
//                        $ratioName => (int) $ratio[$ratioName]
//                    ];
//                    $lowestVal = $minVal;
//                } else {
//                    if ($itemRatio > 1) {
//                        $lowestValPair = [
//                            $ratioName => (int) $ratio[$ratioName]
//                        ];
//                        $lowestVal = $minVal;
//                    }
//                }
//            }
//        }
//
//
//
//        info(print_r([
//            '$ratio' => $ratio,
//            '$lowestVal' => $lowestVal,
//            '$lowestValPair' => $lowestValPair,
//            '$pairLimits' => $pairLimits,
//            'limits' => $limits
//        ], true));
//
//        foreach ($limits as $id => $limitItem) {
//            if (isset($lowestValPair[$limitItem['funder'] .'_'. $limitItem['phase']])) {
//                $baseRatio = $lowestValPair[$limitItem['funder'] .'_'. $limitItem['phase']];
//            } else {
//                if ($baseRatio > 1) {
//                    $lots = (float) $limitItem['tp']['lots'] / $endRatio;
//                } else {
//                    $lots = (float) $limitItem['tp']['lots'] * $endRatio;
//                }
//                $limits[$id]['tp']['lots'] = $lots;
//                $limits[$id]['sl']['lots'] = $lots;
//            }
//        }
//
//        info(print_r([
//            'ratio' => $ratio,
//            '$lowestValPair' => $lowestValPair,
//            '$lowestVal' => $lowestVal,
//            '$baseRatio' => $baseRatio,
//        ], true));
//
//        return $limits;
//    }

    public function calculateFproCrossPhaseLimits($pairLimits)
    {
//        info(print_r([
//            'calculateFproCrossPhaseLimits' => $pairLimits
//        ], true));

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
//            info(print_r([
//                '$maxNum' => $maxNum,
//                '$baseLimits' => $baseLimits
//            ], true));
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

    public function getLimits()
    {
        $pairLimits = $this->getItemsLowestTpSl($this->items);

        if (($this->items[0]['trading_account_credential']['package']['funder']['alias'] === 'FPRO' &&
            $this->items[1]['trading_account_credential']['package']['funder']['alias'] === 'FPRO') &&
            $this->items[0]['trading_account_credential']['package']['current_phase'] !== $this->items[1]['trading_account_credential']['package']['current_phase']) {
            return $this->calculateFproCrossPhaseLimits($pairLimits);
        }

//        info(print_r([
//            'getLimits' => $pairLimits
//        ], true));

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
            ];
        }

        $limits = $this->equalizeTpSL($itemIds, $pairLimits, $limits);
        $limits = $this->convertForexTpSl($itemIds, $pairLimits, $limits);
        $limits = $this->scaleDownFunderPro($itemIds, $pairLimits, $limits);

        return $limits;
    }

    public function getFunderRatio($itemIds)
    {
        $ratio = [
            'fpro_phase-2_upft_phase-2' => '1:2',
            'fpro_phase-2_gff_phase-2' => '1:2',

            'fpro_phase-3_fpro_phase-2' => '1:4',
            'fpro_phase-3_upft_phase-3' => '1:2',
            'fpro_phase-3_gff_phase-3' => '1:2',

            'pipf_phase-2_upft_phase-2' => '1:2',
            'pipf_phase-2_gff_phase-2' => '1:2',

            'pipf_phase-3_pipf_phase-2' => '1:4',
            'pipf_phase-3_upft_phase-3' => '1:2',
            'pipf_phase-3_gff_phase-3' => '1:2',
        ];

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
                    $fndr_1 .'_'. $pckg_1['current_phase'] => $val[0],
                    $fndr_2 .'_'. $pckg_2['current_phase'] => $val[1]
                ];

                asort($funderRatio);
                return $funderRatio;
            }

            if ($key === $search2) {

                $funderRatio = [
                    $fndr_1 .'_'. $pckg_1['current_phase'] => $val[1],
                    $fndr_2 .'_'. $pckg_2['current_phase'] => $val[0]
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

//            $item1Tp = $this->convertUnitsToLots($limits[$pairLimits[0]['id']]['tp']['amount'], $lotsEquityBracket);
//            $generatedLots = $item1Tp['lots'];
//
//            $limits[$pairLimits[0]['id']]['tp'] = $item1Tp;
//            $limits[$pairLimits[0]['id']]['sl'] = $this->convertUnitsToLots($limits[$pairLimits[0]['id']]['sl']['amount'], $lotsEquityBracket, $generatedLots);
//            $limits[$pairLimits[1]['id']]['tp'] = $this->convertUnitsToLots($limits[$pairLimits[1]['id']]['tp']['amount'], $lotsEquityBracket, $generatedLots);
//            $limits[$pairLimits[1]['id']]['sl'] = $this->convertUnitsToLots($limits[$pairLimits[1]['id']]['sl']['amount'], $lotsEquityBracket, $generatedLots);

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

        foreach ($limits as $itemId => $limitItem) {
            if ($limitItem['funder'] === 'pipf') {
                $newTpTick = (float) $limitItem['tp']['ticks'] / 100;
                $newTpTick = floor($newTpTick * 100) / 100;

                $newSlTick = (float) $limitItem['sl']['ticks'] / 100;
                $newSlTick = floor($newSlTick * 100) / 100;

                $limits[$itemId]['tp']['ticks'] = number_format($newTpTick, 2, '.', '');
                $limits[$itemId]['sl']['ticks'] = number_format($newSlTick, 2, '.', '');
            }
        }

        return $limits;
    }
}
