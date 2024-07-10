<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class TradingUnitsModel extends Model
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'trading_units';
    protected $fillable = [
        'name',
        'ip_address'
    ];
}
