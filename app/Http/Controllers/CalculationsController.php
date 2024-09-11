<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CalculationsController extends Controller
{
    public static $percentMSL = 0.02; // as a fraction
    public static $lotPercent = 0.8; // as a fraction
    public static $stopLossPip = 5.1;

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
