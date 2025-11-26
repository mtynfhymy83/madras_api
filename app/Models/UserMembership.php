<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMembership extends Model
{
    use HasFactory;

    protected $table = 'ci_user_membership';

    protected $fillable = [
        'user_id',
        'factor_id',
        'membership_id',
        'startdate',
        'enddate',
    ];

    protected function casts(): array
    {
        return [
            'startdate' => 'date',
            'enddate' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

