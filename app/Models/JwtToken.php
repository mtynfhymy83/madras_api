<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JwtToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'refresh_token',
        'mini_app_uuid',
        'roles',
        'permissions',
        'expires_at',
        'last_used_at',
        'device_info',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the JWT token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the token is valid (not expired).
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Scope to get valid tokens.
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get tokens for a specific mini-app.
     */
    public function scopeForMiniApp($query, string $miniAppUuid)
    {
        return $query->where('mini_app_uuid', $miniAppUuid);
    }
}