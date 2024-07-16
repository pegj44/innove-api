<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingIndividual extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trading_unit_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tradingUnit()
    {
        return $this->belongsTo(TradingUnitsModel::class, 'trading_unit_id');
    }

    public function metadata()
    {
        return $this->hasMany(TradingIndividualMetadata::class);
    }
}
