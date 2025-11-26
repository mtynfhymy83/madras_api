<?php

namespace App\Contracts\Services;

use App\Models\User;

interface EitaaAuthServiceInterface
{
    /**
     * Handle Eitaa authentication callback and generate Sanctum token.
     *
     * @param  array<string, mixed>  $validatedData
     * @return array<string, mixed>
     */
    public function handleCallback(array $validatedData): array;

    /**
     * Link phone number to authenticated user.
     *
     * @param  array<string, mixed>  $validatedData
     */
    public function linkPhoneNumber(array $validatedData, User $user): User;

    /**
     * Refresh Sanctum token.
     *
     * @return array<string, mixed>
     */
    public function refreshToken(string $miniAppUuid, User $user): array;

    /**
     * Logout and delete current Sanctum token.
     */
    public function logout(User $user): void;
}