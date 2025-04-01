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
        'account_id',
        'name',
        'unit_id',
        'status'
    ];

    public function account()
    {
        return $this->belongsTo(AccountModel::class, 'account_id');
    }

    public function userAccounts()
    {
        return $this->hasMany(TradingIndividual::class, 'trading_unit_id');
    }
}
