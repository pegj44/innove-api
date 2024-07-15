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
}
