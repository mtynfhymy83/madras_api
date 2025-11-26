<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMeta extends Model
{
    use HasFactory;

    protected $table = 'ci_user_meta';

    protected $fillable = [
        'user_id',
        'meta_name',
        'meta_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

