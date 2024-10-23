<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'trade_account_credential_id',
        'amount_requested',
        'status',
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
