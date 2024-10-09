<?php

namespace App\Http\Controllers;

use App\Models\PairedItems;
use Illuminate\Http\Request;

class InvestorController extends Controller
{
    public function getOngoingTrades()
    {
        $items = PairedItems::with([
            'tradeReportPair1.tradingAccountCredential.userAccount.tradingUnit',
            'tradeReportPair1.tradingAccountCredential.funder.metadata',
            'tradeReportPair2.tradingAccountCredential.userAccount.tradingUnit',
            'tradeReportPair2.tradingAccountCredential.funder.metadata'
        ])
            ->where('account_id', auth()->user()->account_id)
            ->where('status', 'trading')
            ->get();

        return response()->json($items);
    }
}
