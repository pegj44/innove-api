<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfileModel extends Model
{
    use HasFactory;

    protected $table = 'user_profile';
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'company',
        'address',
        'country',
        'profile_image',
    ];

    protected $attributes = [
        'middle_name' => '',
        'company' => '',
        'address' => '',
        'country' => '',
        'profile_image' => ''
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
