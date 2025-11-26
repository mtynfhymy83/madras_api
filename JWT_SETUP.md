# JWT Authentication Setup Guide

This guide will help you set up JWT authentication for your Laravel application.

## Installation Steps

### 1. Install JWT Package

Run the following command to install the JWT package:

```bash
composer require tymon/jwt-auth
```

### 2. Publish JWT Configuration

```bash
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```

### 3. Generate JWT Secret

```bash
php artisan jwt:secret
```

This will add `JWT_SECRET` to your `.env` file.

### 4. Register Middleware

Add the JWT middleware to `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ... existing middleware
    'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
];
```

### 5. Update .env File

Add these to your `.env` file:

```env
JWT_SECRET=your-secret-key-here
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256
JWT_BLACKLIST_ENABLED=true
```

## Usage Examples

### Login Controller Example

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        $result = $this->authService->login(
            $request->username,
            $request->password,
            $request->boolean('remember')
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function logout()
    {
        $this->authService->logout();
        
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

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
            'data' => $result
        ]);
    }

    public function me()
    {
        $user = $this->authService->user();
        
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }
}
```

### Routes Example

```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('jwt.auth');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('jwt.auth');
Route::get('/me', [AuthController::class, 'me'])->middleware('jwt.auth');
```

### Using in Controllers

```php
use App\Services\AuthService;

class YourController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function index()
    {
        $user = $this->authService->user();
        
        // Or use auth()->user() if using Laravel's auth
        // $user = auth('api')->user();
        
        return response()->json(['user' => $user]);
    }
}
```

## Features

### User Model Features

- ✅ JWT authentication support
- ✅ User level/permission system
- ✅ User meta management
- ✅ Online status tracking
- ✅ Avatar/cover image handling
- ✅ Account management

### Security Features

- ✅ Password hashing with bcrypt
- ✅ Token expiration
- ✅ Token refresh mechanism
- ✅ Blacklist support for invalidated tokens
- ✅ Active/approved user checks

## Migration Notes

The User model supports both new hashed passwords and legacy MD5 passwords. When a user logs in with an MD5 password, it will automatically be upgraded to bcrypt.

## Performance Optimizations

- Uses Eloquent relationships for efficient queries
- Indexed database columns for fast lookups
- Caching-friendly structure
- Optimized user meta retrieval

