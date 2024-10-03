<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingUnitQueueModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'unit',
        'machine',
        'queue_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
