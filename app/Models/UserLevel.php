<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLevel extends Model
{
    use HasFactory;

    protected $table = 'ci_user_level';

    protected $fillable = [
        'level_id',
        'level_key',
        'level_value',
    ];

    public $timestamps = false;
}

