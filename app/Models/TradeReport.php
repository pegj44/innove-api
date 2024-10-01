<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'trade_account_credential_id',
        'starting_daily_equity',
        'latest_equity',
        'purchase_type',
        'order_amount',
        'stop_loss_ticks',
        'take_profit_ticks',
        'status',
        'remarks',
    ];

    protected $attributes = [
        'remarks' => '',
        'purchase_type' => '',
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
