<?php

namespace App\Contracts;

use App\Requests\Auth\LoginRequest;
use App\Requests\Auth\EitaaLoginRequest;

interface AuthServiceInterface
{
    public function loginWithCredentials(LoginRequest $request): array;

    public function authenticateWithEitaa(EitaaLoginRequest $request): array;

    public function logout(): bool;

    public function logoutFromAllDevices(int $userId): bool;

    public function getUserProfile(int $userId): ?array;
}
