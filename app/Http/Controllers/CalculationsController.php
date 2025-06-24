<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CalculationsController extends Controller
{
    public static $percentMSL = 0.02; // as a fraction
    public static $lotPercent = 0.8; // as a fraction
    public static $stopLossPip = 5.1;

    public static function adjustLimitsByMarginCap($item, $prices, $limitConfig)
    {
        $symbol = strtolower($item['trading_account_credential']['package']['symbol']);

        if ($symbol === 'xauusd') {
            return self::calculateForexMarginLimit($item, $prices[$symbol], $limitConfig);
        }

        // @todo - Find API service that offers mgc data feed
//        if (contains($item['symbol'], 'mgc')) {
//            return self::calculateMgcMarginLimit($item, $price, $limitConfig);
//        }

        return null;
    }

    public static function calculateForexMarginLimit($item, $current_price, $limitConfig)
    {
        $funderAlias = strtolower($item['trading_account_credential']['package']['funder']['alias']);
        $funderConfigKey = $funderAlias .'_'. $item['trading_account_credential']['package']['current_phase'];

        if (!isset($limitConfig['marginLimits'][$funderConfigKey])) {
            info(print_r([
                'calculateForexMarginLimit_notset' => $funderConfigKey,
            ], true));
            return null;
        }

        if (isset($limitConfig['marginLimits'][$funderConfigKey]['lots'])) {
            return (float) $limitConfig['marginLimits'][$funderConfigKey]['lots'] * (float) $limitConfig['forexTicksRange']['max'];
        }

        $starting_balance = $item['trading_account_credential']['package']['starting_balance'];
        $margin_cap_percent = (float) $limitConfig['marginLimits'][$funderConfigKey]['limit'];
        $contract_size = 100;
        $leverage = (int) $limitConfig['marginLimits'][$funderConfigKey]['leverage'];

        // Calculate max margin allowed
        $max_margin_allowed = ($starting_balance * $margin_cap_percent) / 100;

        // Margin required per 1 lot
        $position_size_per_lot = $contract_size * $current_price; // USD value per lot
        $margin_required_per_lot = $position_size_per_lot / $leverage;

        // Max lots allowed (without exceeding cap)
        $max_lots = floor($max_margin_allowed / $margin_required_per_lot * 100) / 100; // rounded down to 2 decimals

        return $max_lots * (float) $limitConfig['forexTicksRange']['max'];
    }

    public static function calculateForexMarginLimit_old($item, $current_price, $limitConfig)
    {
        info(print_r([
            'calculateForexMarginLimit_1' => $item,
        ], true));

        $lots = (float) $item['tp']['lots'];
        $starting_balance = $item['starting_balance'];
        $leverage = (int) $limitConfig['leverage'];
        $margin_cap_percent = (float) $limitConfig['limit'];
        $contract_size = 100;

        // Calculate max margin allowed
        $max_margin_allowed = ($starting_balance * $margin_cap_percent) / 100;

        // Margin required per 1 lot
        $position_size_per_lot = $contract_size * $current_price; // USD value per lot
        $margin_required_per_lot = $position_size_per_lot / $leverage;

        // Max lots allowed (without exceeding cap)
        $max_lots = floor($max_margin_allowed / $margin_required_per_lot * 100) / 100; // rounded down to 2 decimals

        if ($lots >= $max_lots) {

            $item['tp']['lots'] = $max_lots;
            $item['sl']['lots'] = $max_lots;

            $newTpTicks = (float) $item['tp']['amount'] / $max_lots;
            $newSlTicks = (float) $item['sl']['amount'] / $max_lots;

            $item['tp']['ticks'] = floor($newTpTicks * 100) / 100;
            $item['sl']['ticks'] = floor($newSlTicks * 100) / 100;
        }

        info(print_r([
            'calculateForexMarginLimit_2' => $item,
        ], true));

//        // Calculate Total Position Size
//        $totalOunces = (float) $item['tp']['lots'] * 100;
//        $totalPositionSize = $totalOunces * (float) $price;
//
//        // Calculate Margin Used
//        $marginUsed = $totalPositionSize / (int) $limitConfig['leverage'];
//        $marginPercentage = ($marginUsed / (float) $item['starting_balance']) * 100;
//
//        if ($marginPercentage >= (float) $limitConfig['limit']) {
//            info(print_r([
//                'calculateForexMarginLimit' => 'Margin used is greater than limit. Margin used: ' . $marginPercentage . ' Limit: ' . $limitConfig['limit']
//            ], true));
//        }

        return $item;
    }

    public static function calculateMgcMarginLimit($item, $price, $limitConfig)
    {
        return $item;
    }

    public static function calculateVolume($latestEquity, $stopLossPip)
    {
        $lotQty = $latestEquity * self::$percentMSL * self::$lotPercent / ($stopLossPip * 100);

        return  self::roundUpToTwoDecimalPlaces($lotQty);
    }

    public function calculateTpSl(Request $request)
    {
        $args = parseArgs($request->except('_token'), [
            'latestEquity' => 0,
            'marketPrice' => 0,
            'stopLossPip' => self::$stopLossPip
        ]);

        $lotQty = self::calculateVolume($args['latestEquity'], $args['stopLossPip']);

        // Calculate tPTicks
        $tPTicks = $args['stopLossPip'] - 0.2;
        $tPTicks = self::roundUpToTwoDecimalPlaces($tPTicks);

        // Calculate buyTp and buySL
        $buyTp = $tPTicks + $args['marketPrice'] - 0.2;
        $buySL = $args['marketPrice'] - $tPTicks;

        // Calculate sellTp and sellSl
        $sellTp = $args['marketPrice'] - $args['stopLossPip'] + 0.2;
        $sellSl = $args['marketPrice'] + $tPTicks;

        return [
            'lotVolume' => $lotQty,
            'takeProfitPips' => $tPTicks,
            'buyTakeProfit' => self::roundUpToTwoDecimalPlaces($buyTp),
            'buyStopLoss' => self::roundUpToTwoDecimalPlaces($buySL),
            'sellTakeProfit' => self::roundUpToTwoDecimalPlaces($sellTp),
            'sellStopLoss' => self::roundUpToTwoDecimalPlaces($sellSl)
        ];
    }

    public static function calculateTradeAmounts()
    {

    }

    private static function roundUpToTwoDecimalPlaces($number)
    {
        return ceil($number * 100) / 100;
    }
}
