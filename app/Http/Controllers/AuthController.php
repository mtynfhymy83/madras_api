<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:ci_users,username',
            'email' => 'nullable|email|max:255|unique:ci_users,email',
            'password' => 'required|string|min:6',
            'name' => 'nullable|string|max:50',
            'family' => 'nullable|string|max:50',
            'tel' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'name' => $request->name,
                'family' => $request->family,
                'displayname' => $request->name . ' ' . $request->family,
                'tel' => $request->tel,
                'active' => true,
                'approved' => false, // Require admin approval
                'level' => 'user',
                'register' => 'started',
            ]);

            // Generate JWT token
            $token = auth('api')->login($user);
            
            // Update last seen
            $user->updateLastSeen();

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                    'user' => $user->makeHidden(['password']),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->authService->login(
            $request->username,
            $request->password,
            $request->boolean('remember')
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials or account not active'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => $result
        ]);
    }

    /**
     * Logout user
     */
    public function logout()
    {
        $this->authService->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Refresh JWT token
     */
    public function refresh()
    {
        $result = $this->authService->refresh();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh token'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => $result
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me()
    {
        $user = $this->authService->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $user->makeHidden(['password'])
        ]);
    }
}

