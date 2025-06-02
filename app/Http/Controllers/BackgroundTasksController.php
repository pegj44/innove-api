<?php

namespace App\Http\Controllers;

use App\Models\TradeQueueModel;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BackgroundTasksController extends Controller
{
    public function cleanUpTradeQueue()
    {
        $daysAgo = 60;
        $limit = 1000;

        // Get the cutoff date (90 days ago)
        $cutoffDate = Carbon::now()->subDays($daysAgo);

        $itemsToDelete = TradeQueueModel::where('created_at', '<', $cutoffDate)
            ->limit($limit)
            ->get();

        $countDeleted = $itemsToDelete->count();
        TradeQueueModel::destroy($itemsToDelete->pluck('id'));

        $remaining = TradeQueueModel::where('created_at', '<', $cutoffDate)->count();

        echo "$countDeleted items deleted.\n";
        echo "$remaining items remaining to be deleted.\n";
    }
}
