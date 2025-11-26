<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthService
{
    /**
     * Authenticate user and return JWT token
     *
     * @param string $username
     * @param string $password
     * @param bool $remember
     * @return array|null
     */
    public function login(string $username, string $password, bool $remember = false): ?array
    {
        $user = User::authenticate($username, $password);

        if (!$user) {
            return null;
        }

        try {
            $token = JWTAuth::fromUser($user);
            
            // Set longer expiration if remember me
            if ($remember) {
                $customClaims = ['exp' => now()->addDays(10)->timestamp];
                $token = JWTAuth::customClaims($customClaims)->fromUser($user);
            }

            return [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => $user->makeHidden(['password']),
            ];
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Refresh JWT token
     *
     * @return array|null
     */
    public function refresh(): ?array
    {
        try {
            $token = JWTAuth::refresh();
            $user = JWTAuth::user();

            return [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => $user->makeHidden(['password']),
            ];
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Logout user (invalidate token)
     *
     * @return bool
     */
    public function logout(): bool
    {
        try {
            $user = JWTAuth::user();
            
            if ($user) {
                // Remove from online users
                \DB::table('ci_onlines')->where('user_id', $user->id)->delete();
            }

            JWTAuth::invalidate();
            return true;
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * Get authenticated user
     *
     * @return User|null
     */
    public function user(): ?User
    {
        try {
            return JWTAuth::user();
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function check(): bool
    {
        try {
            return JWTAuth::check();
        } catch (JWTException $e) {
            return false;
        }
    }
}

