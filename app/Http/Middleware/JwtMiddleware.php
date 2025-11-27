<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Check if user is active and approved
            if (!$user->active || !$user->approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'User account is not active or approved'
                ], 403);
            }

            // Update last seen
            $user->updateLastSeen();

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired'
            ], 401);

        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid'
            ], 401);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token absent'
            ], 401);
        }

        return $next($request);
    }
}



