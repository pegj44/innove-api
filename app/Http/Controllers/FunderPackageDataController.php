<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FunderPackageDataController extends Controller
{
    public $package;

    public function __construct($item)
    {
        $itemData = $item;

        if (is_object($item)) {
            $itemData = $item->toArray();
        }

        $this->package = $itemData['trading_account_credential']['package'];
    }

    public function getPackageName()
    {
        return $this->package['name'];
    }

    public function getFunderName()
    {
        return $this->package['funder']['name'];
    }

    public function getFunderAlias()
    {
        return $this->package['funder']['alias'];
    }

    public function getFunderTheme()
    {
        return $this->package['funder']['theme'];
    }

    public function getFunderData()
    {
        return $this->package['funder'];
    }

    public function getAssetType()
    {
        return $this->package['asset_type'];
    }

    public function getSymbol()
    {
        return $this->package['symbol'];
    }

    public function getPhase()
    {
        return $this->package['current_phase'];
    }

    public function getStartingBalance()
    {
        return (float) $this->package['starting_balance'];
    }

    public function getDrawdownType()
    {
        return $this->package['drawdown_type'];
    }

    public function getTotalTargetProfit()
    {
        return (float) $this->package['total_target_profit'];
    }

    public function getPerTradeTargetProfit()
    {
        return (float) $this->package['per_trade_target_profit'];
    }

    public function getDailyTargetProfit()
    {
        return (float) $this->package['daily_target_profit'];
    }

    public function getMaxDrawdown()
    {
        return (float) $this->package['max_drawdown'];
    }

    public function getPerTradeDrawdown()
    {
        return (float) $this->package['per_trade_drawdown'];
    }

    public function getDailyDrawdown()
    {
        return (float) $this->package['daily_drawdown'];
    }

    public function getMinimumTradingDays()
    {
        return (integer) $this->package['minimum_trading_days'];
    }

    public function getPositiveTradingDaysAmount()
    {
        return (float) $this->package['positive_trading_days_amount'];
    }

    public function getPlatformType()
    {
        return $this->package['platform_type'];
    }

    public function getAllData()
    {

    }
}
