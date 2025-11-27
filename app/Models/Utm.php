<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Utm extends Model
{
    use HasFactory;

    protected $table = 'utm';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'eitaa_id',
        'is_registered',
        'utm',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_registered' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


