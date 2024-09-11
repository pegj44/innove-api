<?php

namespace App\Http\Controllers;

use App\Events\UnitsEvent;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function initiateTrade(Request $request)
    {
        info(print_r([
            'initiateTrade' => $request->all()
        ], true));

        foreach ($request->except('_token') as $item) {
            UnitsEvent::dispatch(auth()->id(), [
                'latest_equity' => $item['latest_equity'],
                'purchase_type' => $item['purchase_type']
            ], 'initiate-trade', $item['machine'], $item['ip']);
        }

        return response()->json(['message' => __('Initiating Unit')]);
    }
}
