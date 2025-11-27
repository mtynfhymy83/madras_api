<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthService
{
  
    public function __construct(
        private readonly EitaaService $eitaaService,
        private readonly JwtService $jwtService
    ) {}

    public function handleCallback(array $validatedData, ?string $userAgent = null): array
    {
        // 1) Get mini-app from cache
        $miniAppUuid = $validatedData['mini_app_uuid'];
        $miniAppToken = $this->eitaaService->getMiniAppToken($miniAppUuid);

        // 2) Handle the Eitaa payload
        $eitaaData = preg_replace('/\\\\"/', '"', $validatedData['eitaa_data']);
        $validate_data = $this->eitaaService->validateEitaaData($eitaaData, $miniAppToken);
        if (! $validate_data) {
            throw new InvalidEitaaDataException('Invalid Eitaa data', 400);
        }
        $parsed = $this->eitaaService->extractData($eitaaData);

        // Store previous team context
        $previousTeamId = getPermissionsTeamId();

        try {
            // Set the team context for this mini-app
            setPermissionsTeamId($miniAppUuid);

            // 3) Create/update user with eager loaded relationships
            $user = $this->eitaaService->createOrGetUserInMiniApp($parsed['user'], $miniAppUuid);

            // 4) Generate JWT token pair
            $tokens = $this->jwtService->generateTokenPair(
                $user,
                $miniAppUuid,
                $userAgent
            );

            $user->load(['media']);

            // 5) Return structured response (user details are in JWT payload)
            return [
                'user' => $user,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => $tokens['expires_in'],
                'token_type' => $tokens['token_type'],
                'mini_app_uuid' => $tokens['mini_app_uuid'],
            ];
        } finally {
            // Always restore the previous team context
            setPermissionsTeamId($previousTeamId);
        }
    }

    public function linkPhoneNumber(array $validatedData, User $user): User
    {
        $miniAppUuid = $validatedData['mini_app_uuid'];
        $miniAppToken = $this->eitaaService->getMiniAppToken($miniAppUuid);

        // authenticate contact data
        $validate_data = $this->eitaaService->validateEitaaData(
            preg_replace('/\\\\"/', '"', $validatedData['eitaa_data']),
            $miniAppToken
        );

        if (! $validate_data) {
            throw new InvalidEitaaDataException('Invalid Contact data', 400);
        }

        $phone = $this->eitaaService->extractPhoneNumber($validatedData['contact_data']);
        $phoneNumber = $this->eitaaService->normalizePhoneNumber(
            $phone ?? ''
        );

        // Update only the phone on a persisted user record
        if (! $user || ! $user->id) {
            throw new \InvalidArgumentException('User not found');
        }

        $user->phone = $phoneNumber;
        $user->save();

        // Cache the user data after phone number is updated
        $cacheKey = "user.eitaa.{$user->eitaa_id}.{$miniAppUuid}";
        cache()->put($cacheKey, $user, now()->addMinutes(15));

        return $user;
    }

    public function refreshToken(string $refreshToken, string $miniAppUuid): array
    {
        return $this->jwtService->refreshAccessToken($refreshToken, $miniAppUuid);
    }

    public function logout(?string $refreshToken = null, ?string $miniAppUuid = null): bool
    {
        if ($refreshToken && $miniAppUuid) {
            return $this->jwtService->revokeToken($refreshToken, $miniAppUuid);
        }

        return true;
    }
}