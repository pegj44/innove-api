<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingAccountsModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'funder',
        'account',
        'initial',
        'phase',
        'starting_balance',
        'starting_daily_equity',
        'latest_equity',
        'status',
        'target_profit',
        'remarks'
    ];
}
