<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundersMetadata extends Model
{
    use HasFactory;

    protected $fillable = [
        'funder_id',
        'key',
        'value'
    ];

    public function funder()
    {
        return $this->belongsTo(Funder::class);
    }
}
