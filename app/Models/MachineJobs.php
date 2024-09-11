<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineJobs extends Model
{
    use HasFactory;

    protected $table = 'machine_jobs';

    protected $fillable = [
        'user_id',
        'machine_name',
        'ip',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
