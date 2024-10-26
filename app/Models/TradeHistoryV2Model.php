<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeHistoryV2Model extends Model
{
    use HasFactory;

    protected $table = 'trade_history2';

    protected $fillable = [
        'account_id',
        'trade_account_credential_id',
        'starting_daily_equity',
        'latest_equity',
        'highest_equity',
        'status'
    ];

    protected $attributes = [
        'starting_daily_equity' => 0,
        'latest_equity' => 0,
        'highest_equity' => 0,
        'status' => ''
    ];

    public function account()
    {
        return $this->belongsTo(AccountModel::class, 'account_id');
    }

    public function tradingAccountCredential()
    {
        return $this->belongsTo(TradingAccountCredential::class, 'trade_account_credential_id');
    }
}
