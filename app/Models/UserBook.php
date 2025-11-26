<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBook extends Model
{
    use HasFactory;

    protected $table = 'ci_user_books';

    protected $fillable = [
        'user_id',
        'book_id',
        'factor_id',
        'need_update',
        'expiremembership',
    ];

    protected function casts(): array
    {
        return [
            'expiremembership' => 'date',
            'need_update' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

