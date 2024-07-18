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
        'dashboard_login_url',
        'dashboard_login_username',
        'dashboard_login_password',
        'platform_login_url',
        'platform_login_username',
        'platform_login_password',
        'status'
    ];
}
