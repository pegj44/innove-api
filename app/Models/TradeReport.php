<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trading_unit_id',
        'funder_id',
        'trade_account_credential_id',
        'starting_balance',
        'starting_equity',
        'latest_equity',
        'status',
        'remarks',
        'purchase_type',
        'order_type',
        'order_amount',
        'stop_loss_ticks',
        'take_profit_ticks',
    ];

    protected $attributes = [
        'remarks' => '',
        'purchase_type' => '',
        'order_type' => '',
        'order_amount' => '',
        'stop_loss_ticks' => '',
        'take_profit_ticks' => '',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tradeCredential()
    {
        return $this->belongsTo(TradingAccountCredential::class, 'trade_account_credential_id');
    }

    public function tradingAccountCredential()
    {
        return $this->belongsTo(TradingAccountCredential::class, 'trade_account_credential_id');
    }

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }
}
