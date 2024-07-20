<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Funder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function metadata()
    {
        return $this->hasMany(FundersMetadata::class);
    }

    public function credentials()
    {
        return $this->hasMany(TradingAccountCredential::class, 'funder_id');
    }

    public function tradeReports()
    {
        return $this->hasMany(TradeReport::class);
    }
}
