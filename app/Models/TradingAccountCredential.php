<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingAccountCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'user_account_id',
        'funder_id',
        'funder_account_id',
        'starting_balance',
        'asset_type',
        'symbol',
        'current_phase',
        'phase_1_total_target_profit',
        'phase_1_daily_target_profit',
        'phase_1_max_drawdown',
        'phase_1_daily_drawdown',
        'phase_2_total_target_profit',
        'phase_2_daily_target_profit',
        'phase_2_max_drawdown',
        'phase_2_daily_drawdown',
        'phase_3_total_target_profit',
        'phase_3_daily_target_profit',
        'phase_3_max_drawdown',
        'phase_3_daily_drawdown',
        'status',
        'platform_type',
        'platform_url',
        'platform_login_username',
        'platform_login_password',
        'drawdown_type',
        'priority',
        'funder_package_id'
    ];

    public $attributes = [
        'phase_1_total_target_profit' => '',
        'phase_1_daily_target_profit' => '',
        'phase_1_max_drawdown' => '',
        'phase_1_daily_drawdown' => '',
        'phase_2_total_target_profit' => '',
        'phase_2_daily_target_profit' => '',
        'phase_2_max_drawdown' => '',
        'phase_2_daily_drawdown' => '',
        'phase_3_total_target_profit' => '',
        'phase_3_daily_target_profit' => '',
        'phase_3_max_drawdown' => '',
        'phase_3_daily_drawdown' => '',
        'status' => 'active',
        'platform_url' => '',
        'platform_login_username' => '',
        'platform_login_password' => '',
        'drawdown_type' => '',
        'priority' => '',
//        'funder_id' => 0,
        'starting_balance' => '',
        'asset_type' => '',
        'symbol' => '',
        'current_phase' => '',
        'platform_type' => '',
    ];

    public function account()
    {
        return $this->belongsTo(AccountModel::class, 'account_id');
    }

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }

    public function package()
    {
        return $this->belongsTo(FunderPackagesModel::class, 'funder_package_id');
    }

    public function userAccount()
    {
        return $this->belongsTo(TradingIndividual::class, 'user_account_id');
    }

    public function tradeReports()
    {
        return $this->hasOne(TradeReport::class, 'trade_account_credential_id');
    }

    public function history()
    {
        return $this->hasOne(TradeHistoryModel::class, 'trade_account_credential_id');
    }

    public function historyV2()
    {
        return $this->hasMany(TradeHistoryV2Model::class, 'trade_account_credential_id');
    }

    public function historyV3()
    {
        return $this->hasMany(TradeHistoryV3Model::class, 'trade_account_credential_id');
    }

    public function payouts()
    {
        return $this->hasMany(PayoutModel::class, 'trade_account_credential_id');
    }
}
