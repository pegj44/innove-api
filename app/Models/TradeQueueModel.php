<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeQueueModel extends Model
{
    use HasFactory;

    protected $table = 'trade_queue';
    protected $fillable = [
        'account_id',
        'queue_id',
        'data',
        'status',
        'unit_ready' => ''
    ];

    protected $attributes = [
        'data' => '',
        'status' => '',
        'unit_ready' => ''
    ];

    public function account()
    {
        return $this->belongsTo(AccountModel::class, 'account_id');
    }
}
