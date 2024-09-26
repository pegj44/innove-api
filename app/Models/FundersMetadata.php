<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundersMetadata extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $casts = [
        'value' => 'array'
    ];

    protected $fillable = [
        'funder_id',
        'key',
        'value'
    ];

    public static $defaultMetadata = [

    ];

    public function funder()
    {
        return $this->belongsTo(Funder::class);
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
