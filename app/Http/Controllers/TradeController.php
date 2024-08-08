<?php

namespace App\Http\Controllers;

use App\Events\UnitsEvent;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function initiateTrade(Request $request)
    {
        UnitsEvent::dispatch(auth()->id(), [
            'unit1' => $request->get('unit1'),
            'unit2' => $request->get('unit2')
        ], 'initiate-trade');
        return response()->json(['message' => __('Initiating Unit')]);
    }
}
