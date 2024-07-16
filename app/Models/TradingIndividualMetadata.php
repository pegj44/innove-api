<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingIndividualMetadata extends Model
{
    use HasFactory;

    protected $fillable = [
        'trading_individual_id',
        'key',
        'value'
    ];

    public function tradingIndividuals()
    {
        return $this->belongsTo(TradingIndividual::class, 'trading_individual_id');
    }
}
