<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeHistoryV3Model extends Model
{
    use HasFactory;

    protected $table = 'trade_history3';

    protected $fillable = [
        'trade_account_credential_id',
        'starting_daily_equity',
        'latest_equity',
        'status'
    ];

    protected $attributes = [
        'starting_daily_equity' => 0,
        'latest_equity' => 0,
        'highest_balance' => 0,
        'status' => ''
    ];

    public function tradingAccountCredential()
    {
        return $this->belongsTo(TradingAccountCredential::class, 'trade_account_credential_id');
    }
}
