<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingNewsModel extends Model
{
    use HasFactory;

    protected $table = 'trading_news';

    protected $fillable = [
        'country',
        'impact',
        'title',
        'event_date',
    ];
}
