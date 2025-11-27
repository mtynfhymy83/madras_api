<?php

namespace App\Services\Auth;

use App\Models\JwtToken;
use App\Models\User;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtService
{
    /**
     * Generate JWT token pair (access + refresh tokens).
     */
    public function generateTokenPair(User $user, ?string $deviceInfo = null): array
    {
        // OPTIMIZATION: Load user with roles efficiently and cache the result (if roles relationship exists)
        if (method_exists($user, 'roles') && ! $user->relationLoaded('roles')) {
            $user->load(['roles' => function ($query) {
                $query->select(['roles.id', 'roles.name']);
            }]);
        }

        // blacklist access tokens
        $this->blacklistAccessTokens();

        // Get roles if relationship exists, otherwise use empty array
        $roles = [];
        if (method_exists($user, 'roles') && $user->relationLoaded('roles')) {
            $roles = $user->roles->pluck('name')->toArray();
        } elseif (method_exists($user, 'roles')) {
            $roles = $user->roles()->pluck('name')->toArray();
        }

        // Get eitaa_id from user model or user_meta
        $eitaaId = $user->eitaa_id ?? null;
        if (!$eitaaId && method_exists($user, 'getMeta')) {
            $meta = $user->getMeta();
            $eitaaId = $meta->eitaa_id ?? null;
        }

        // Create custom claims with user details (no UUID)
        $customClaims = [
            'user_id' => $user->id,
            'eitaa_id' => $eitaaId,
            'roles' => $roles,
            'is_super_admin' => (bool) ($user->is_super_admin ?? false),
            'device_info' => $deviceInfo,
        ];

        // Generate access token with custom claims
        $accessToken = JWTAuth::customClaims($customClaims)->fromUser($user);

        // Generate refresh token
        $refreshToken = $this->generateRefreshToken($user, $deviceInfo, $customClaims);

        // Ensure ttl is a valid integer
        $ttl = config('jwt.ttl', 60);
        $ttl = is_numeric($ttl) ? (int) $ttl : 60;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $ttl * 60,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Refresh access token using refresh token.
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $hashedToken = hash('sha256', $refreshToken);
        $jwtToken = JwtToken::where('refresh_token', $hashedToken)
            ->valid()
            ->first();

        if (! $jwtToken) {
            throw new \Illuminate\Auth\AuthenticationException('Invalid refresh token');
        }

        $user = User::find($jwtToken->user_id);
        $deviceInfo = $jwtToken->device_info;

        // Generate new access token but keep the same refresh token
        return $this->generateAccessTokenOnly($user, $deviceInfo, $jwtToken->roles, $refreshToken);
    }

    /**
     * Revoke a refresh token.
     */
    public function revokeToken(string $refreshToken): bool
    {
        $hashedToken = hash('sha256', $refreshToken);

        return JwtToken::where('refresh_token', $hashedToken)
            ->delete() > 0;
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllUserTokens(User $user): int
    {
        return JwtToken::where('user_id', $user->id)
            ->delete();
    }

    /**
     * Blacklist access tokens.
     */
    private function blacklistAccessTokens(): void
    {
        // invalidate access tokens if there's a token in the current request
        try {
            $token = JWTAuth::getToken();
            if ($token) {
                JWTAuth::invalidate($token);
            }
        } catch (\Exception $e) {
            // No token to invalidate, which is fine during token creation
        }
    }

    /**
     * Clean up expired tokens.
     */
    public function cleanupExpiredTokens(): int
    {
        return JwtToken::where('expires_at', '<', now())->delete();
    }

    /**
     * Generate only access token (for refresh token flow).
     */
    private function generateAccessTokenOnly(User $user, ?string $deviceInfo, array $roles, string $refreshToken): array
    {
        // blacklist access tokens
        $this->blacklistAccessTokens();

        // Get eitaa_id from user model or user_meta
        $eitaaId = $user->eitaa_id ?? null;
        if (!$eitaaId && method_exists($user, 'getMeta')) {
            $meta = $user->getMeta();
            $eitaaId = $meta->eitaa_id ?? null;
        }

        // Create custom claims with user details (no UUID)
        $customClaims = [
            'user_id' => $user->id,
            'eitaa_id' => $eitaaId,
            'roles' => $roles,
            'is_super_admin' => (bool) ($user->is_super_admin ?? false),
            'device_info' => $deviceInfo,
        ];

        // Generate access token with custom claims
        $accessToken = JWTAuth::customClaims($customClaims)->fromUser($user);

        // Ensure ttl is a valid integer
        $ttl = config('jwt.ttl', 60);
        $ttl = is_numeric($ttl) ? (int) $ttl : 60;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken, // Keep existing refresh token (original, not hashed)
            'expires_in' => $ttl * 60,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Generate refresh token and store in database.
     */
    private function generateRefreshToken(User $user, ?string $deviceInfo = null, array $customClaims = []): string
    {
        $refreshToken = Str::random(64);

        // Ensure refresh_ttl is a valid integer
        $refreshTtl = config('jwt.refresh_ttl', 20160);
        $refreshTtl = is_numeric($refreshTtl) ? (int) $refreshTtl : 20160;

        JwtToken::create([
            'user_id' => $user->id,
            'refresh_token' => hash('sha256', $refreshToken),
            'roles' => $customClaims['roles'] ?? [],
            'permissions' => $customClaims['permissions'] ?? [],
            'expires_at' => now()->addMinutes($refreshTtl),
            'device_info' => $deviceInfo,
        ]);

        return $refreshToken;
    }

    /**
     * Get user from JWT token.
     */
    public function getUserFromToken(string $token): ?User
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();

            return User::find($payload->get('sub'));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate JWT token.
     */
    public function validateToken(string $token): bool
    {
        try {
            JWTAuth::setToken($token)->getPayload();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}