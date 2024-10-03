<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PairedItems extends Model
{
    use HasFactory;

    protected $table = 'paired_items';
    protected $fillable = [
        'account_id',
        'pair_1',
        'pair_2',
        'status'
    ];

    public function account()
    {
        return $this->belongsTo(AccountModel::class, 'account_id');
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
