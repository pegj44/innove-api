<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingAccountCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trading_individual_id',
        'funder_id',
        'account_id',
        'phase',
        'dashboard_login_username',
        'dashboard_login_password',
        'platform_login_username',
        'platform_login_password',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }

    public function tradingIndividual()
    {
        return $this->belongsTo(TradingIndividual::class);
    }

    public function tradeReports()
    {
        return $this->hasMany(TradeReport::class);
    }
}
