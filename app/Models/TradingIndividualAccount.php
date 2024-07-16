<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingIndividualAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id'
    ];

    public function metadata()
    {
        return $this->hasMany(TradingIndividualMetadata::class, 'trading_individual_id');
    }
}
