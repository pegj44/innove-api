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

    public function metadata()
    {
        return $this->hasMany(FundersMetadata::class);
    }
}
