<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountsPairingJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'status'
    ];

    public function users()
    {
        return $this->belongsTo(User::class);
    }
}
