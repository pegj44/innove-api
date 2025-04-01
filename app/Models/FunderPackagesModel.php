<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FunderPackagesModel extends Model
{
    use HasFactory;

    protected $table = 'funder_packages';
    protected $fillable = [
        'account_id',
        'name',
        'funder_id',
        'asset_type',
        'symbol',
        'current_phase',
        'starting_balance',
        'drawdown_type',
        'total_target_profit',
        'per_trade_target_profit',
        'max_per_trade_target_profit',
        'daily_target_profit',
        'max_drawdown',
        'per_trade_drawdown',
        'max_per_trade_drawdown',
        'daily_drawdown',
        'minimum_trading_days',
        'platform_type',
        'consistency'
    ];

    protected $attributes = [
        'consistency' => 0
    ];

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }

    public function tradingAccountCredentials()
    {
        return $this->hasMany(FunderPackagesModel::class, 'funder_package_id');
    }
}
