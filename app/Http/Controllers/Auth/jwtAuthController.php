<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\Services\JwtAuthServiceInterface;
use App\Helpers\AuthHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\EitaaCallbackRequest;
use App\Http\Requests\Api\Auth\JwtLogoutRequest;
use App\Http\Requests\Api\Auth\JwtRefreshRequest;
use App\Http\Requests\Api\Auth\LinkPhoneRequest;
use App\Http\Resources\Api\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class JwtAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private JwtAuthServiceInterface $jwtAuthService
    ) {}

    public function handleCallback(EitaaCallbackRequest $request): JsonResponse
    {
        $result = $this->jwtAuthService->handleCallback(
            $request->validated(),
            $request->header('User-Agent')
        );

        return $this->success([
            'user' => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
            'token_type' => $result['token_type'],
            'mini_app_uuid' => $result['mini_app_uuid'],
        ], 'User authenticated successfully');
    }

    public function linkPhoneNumber(LinkPhoneRequest $request): JsonResponse
    {
        $user = AuthHelper::getAuthenticatedUser();
        if (! $user || ! $user->id) {
            return $this->error('User not found', 404);
        }

        try {
            $user = $this->jwtAuthService->linkPhoneNumber($request->validated(), $user);

            return $this->success(
                new UserResource($user),
                'Phone number linked successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function refreshToken(JwtRefreshRequest $request): JsonResponse
    {
        // Get mini-app UUID from header (validated in request class)
        $miniAppUuid = $request->header('Miniapp-UUID');

        $tokens = $this->jwtAuthService->refreshToken($request->refresh_token, $miniAppUuid);

        return $this->success([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'token_type' => $tokens['token_type'],
            'mini_app_uuid' => $tokens['mini_app_uuid'],
        ], 'Token refreshed successfully');
    }

    public function logout(JwtLogoutRequest $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');
        $miniAppUuid = $request->header('Miniapp-UUID');

        $this->jwtAuthService->logout($refreshToken, $miniAppUuid);

        return $this->success(null, 'Successfully logged out');
    }
}