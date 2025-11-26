<?php

namespace App\Contracts\Services;

use App\Models\User;

interface JwtAuthServiceInterface
{
    /**
     * Handle Eitaa authentication callback and generate JWT tokens.
     *
     * @param  array<string, mixed>  $validatedData
     * @return array<string, mixed>
     */
    public function handleCallback(array $validatedData, ?string $userAgent = null): array;

    /**
     * Link phone number to authenticated user.
     *
     * @param  array<string, mixed>  $validatedData
     */
    public function linkPhoneNumber(array $validatedData, User $user): User;

    /**
     * Refresh JWT access token.
     *
     * @return array<string, mixed>
     */
    public function refreshToken(string $refreshToken, string $miniAppUuid): array;

    /**
     * Revoke JWT tokens.
     */
    public function logout(?string $refreshToken = null, ?string $miniAppUuid = null): bool;
}