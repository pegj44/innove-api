<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserUnitLogin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'unit_user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

//    public function unitUser()
//    {
//        return $this->belongsTo(User::class, 'unit_user_id');
//    }
}
