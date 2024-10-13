<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingIndividual extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'trading_unit_id',
        'first_name',
        'middle_name',
        'last_name',
    ];

    public $metaData;

    public function account()
    {
        return $this->belongsTo(AccountModel::class);
    }

    public function tradingUnit()
    {
        return $this->belongsTo(TradingUnitsModel::class, 'trading_unit_id');
    }

    public function metadata()
    {
        return $this->hasMany(TradingIndividualMetadata::class);
    }

    public function credentials()
    {
        return $this->hasMany(TradingAccountCredential::class, 'individual_id');
    }

    public function funderAccountCredential()
    {
        return $this->hasMany(FunderAccountCredentialModel::class, 'trading_individual_id');
    }

    public function createMeta($data)
    {
        if (empty($data->metaData)) {
            return;
        }

        foreach ($data->metaData as $key => $value) {
            if (!in_array($key, TradingIndividualMetadata::$defaultMetadata)) {
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
            $metaData[] = [
                'trading_individual_id' => $data->id,
                'key' => strip_tags($key),
                'value' => (!empty($value))? strip_tags($value) : ''
            ];
        }

        TradingIndividualMetadata::upsert($metaData, uniqueBy: ['trading_individual_id', 'key'], update: ['value']);
    }
}
