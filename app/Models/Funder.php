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

    public $metaData;

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

    public function createMeta($data)
    {
        if (empty($data->metaData)) {
            return;
        }

        foreach ($data->metaData as $key => $value) {
            if (!in_array($key, FundersMetadata::$defaultMetadata)) {
                continue;
            }
            $this->metadata()->create([
                'key' => strip_tags($key),
                'value' => (!empty($value))? strip_tags($value) : ''
            ]);
        }
    }

    public function updateMetadata($data)
    {
        if (empty($data->metaData)) {
            return;
        }

        $metaData = [];

        foreach ($data->metaData as $key => $value) {
            if (!in_array($key, FundersMetadata::$defaultMetadata)) {
                continue;
            }
            $metaData[] = [
                'funder_id' => $data->id,
                'key' => strip_tags($key),
                'value' => (!empty($value))? strip_tags($value) : ''
            ];
        }

        FundersMetadata::upsert($metaData, uniqueBy: ['trading_individual_id', 'key'], update: ['value']);
    }
}
