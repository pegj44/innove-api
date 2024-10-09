<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeHistoryModel extends Model
{
    use HasFactory;

    public function tradingAccountCredential()
    {
        return $this->belongsTo(TradingAccountCredential::class, 'trade_account_credential_id');
    }
}
