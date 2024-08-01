<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CalculationsController extends Controller
{
    public function calculateTpSl(Request $request)
    {
        $args = parseArgs($request->except('_token'), [
            'latestEquity' => 0,
            'marketPrice' => 0,
            'stopLossPip' => 5.1
        ]);

        // Given percentages
        $percentMSL = 2; // as a fraction
        $lotPercent = 80; // as a fraction

        // Calculate lotQty
        $lotQty = $args['latestEquity'] * ($percentMSL / 100) * ($lotPercent / 100) / ($args['stopLossPip'] * 100);
        $lotQty = $this->roundUpToTwoDecimalPlaces($lotQty);

        // Calculate tPTicks
        $tPTicks = $args['stopLossPip'] - 0.2;
        $tPTicks = $this->roundUpToTwoDecimalPlaces($tPTicks);

        // Calculate buyTp and buySL
        $buyTp = $tPTicks + $args['marketPrice'] - 0.2;
        $buySL = $args['marketPrice'] - $tPTicks;

        // Calculate sellTp and sellSl
        $sellTp = $args['marketPrice'] - $args['stopLossPip'] + 0.2;
        $sellSl = $args['marketPrice'] + $tPTicks;

        return [
            'lotVolume' => $lotQty,
            'takeProfitPips' => $tPTicks,
            'buyTakeProfit' => $this->roundUpToTwoDecimalPlaces($buyTp),
            'buyStopLoss' => $this->roundUpToTwoDecimalPlaces($buySL),
            'sellTakeProfit' => $this->roundUpToTwoDecimalPlaces($sellTp),
            'sellStopLoss' => $this->roundUpToTwoDecimalPlaces($sellSl)
        ];
    }

    private function roundUpToTwoDecimalPlaces($number)
    {
        return ceil($number * 100) / 100;
    }
}
