<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TradeHistoryV2 extends Controller
{
    public function getHistoryItem(string $id)
    {

    }

    public function store(Request $request)
    {

        return response()->json($request->all());
    }

    public function destroy(string $id)
    {

    }
}
