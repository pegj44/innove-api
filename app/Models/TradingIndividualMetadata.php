<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingIndividualMetadata extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $casts = [
        'value' => 'array'
    ];

    protected $fillable = [
        'trading_individual_id',
        'key',
        'value'
    ];

    public static $defaultMetadata = [
        'type',
        'address',
        'city',
        'province',
        'zip_code',
        'contact_number1',
        'contact_number2',
        'birth_year',
        'birth_month',
        'birth_day',
        'id_type',
        'billing',
        'remarks'
    ];

    public function tradingIndividuals()
    {
        return $this->belongsTo(TradingIndividual::class, 'trading_individual_id');
    }

    /**
     * Check and mutates the meta_value attribute to serialized if value is array.
     *
     * @param $value
     * @return void
     */
    public function setValueAttribute($value)
    {
        $this->attributes['value'] = maybe_serialize($value);
    }

    /**
     * Check and mutates the meta_value attribute to un-serialized if value is array.
     *
     * @param $value
     * @return mixed
     */
    public function getValueAttribute($value)
    {
        return maybe_unserialize($value);
    }
}
