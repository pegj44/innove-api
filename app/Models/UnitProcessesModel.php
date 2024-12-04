<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitProcessesModel extends Model
{
    use HasFactory;

    protected $table = 'unit_processes';
    protected $fillable = [
        'account_id',
        'unit_id',
        'status',
        'process_name',
        'process_type'
    ];
}
