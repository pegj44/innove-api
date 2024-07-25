<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PairedItems extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pair_1',
        'pair_2',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tradeReportPair1()
    {
        return $this->belongsTo(TradeReport::class, 'pair_1');
    }

    public function tradeReportPair2()
    {
        return $this->belongsTo(TradeReport::class, 'pair_2');
    }
}
