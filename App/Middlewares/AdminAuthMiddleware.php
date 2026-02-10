<?php

namespace App\Middlewares;

use App\Auth\JWTAuth;
use App\Traits\ResponseTrait;

class AdminAuthMiddleware
{
    use JWTAuth;
    use ResponseTrait;

    /**
     * Check if user is authenticated as admin
     * 
     * @param mixed $request Swoole Request object or token string
     * @return bool|array Returns false if not authenticated, or user data if authenticated
     */
    public function handle($request)
    {
        $token = $this->getToken($request);
        
        error_log("[AdminAuthMiddleware] Token found: " . ($token ? "Yes (" . substr($token, 0, 20) . "...)" : "No"));
        
        if (!$token) {
            error_log("[AdminAuthMiddleware] No token found, returning false");
            return false;
        }

        // Verify the JWT token
        $decoded = $this->verifyToken($token);
        if (!$decoded) {
            error_log("[AdminAuthMiddleware] Token verification failed");
            return false;
        }
        
        error_log("[AdminAuthMiddleware] Token verified successfully");
        
        // Check if user is admin (level 2 or role admin)
        $level = $decoded->level ?? $decoded->role ?? null;
        $role = $decoded->role ?? null;
        
        $isAdmin = ($level == 2 || $level == '2' || $role == 'admin');
        
        if (!$isAdmin) {
            return false;
        }
        
        // Return user data for use in controller
        return [
            'id' => $decoded->id ?? null,
            'username' => $decoded->username ?? null,
            'level' => $level,
            'role' => $role,
            'data' => $decoded
        ];
    }

    /**
     * Get token from request (Cookie, Header, or Query)
     */
    private function getToken($request): ?string
    {
        // If it's already a token string
        if (is_string($request)) {
            return $request;
        }
        
        // If it's a Swoole Request object
        if (is_object($request) && (property_exists($request, 'cookie') || isset($request->cookie))) {
            // Try query parameter FIRST (for redirect after login - most reliable)
            if (isset($request->get['token']) && !empty($request->get['token'])) {
                $queryToken = $request->get['token'];
                error_log("[AdminAuthMiddleware] Token found in request->get['token'], value: " . substr($queryToken, 0, 20) . "...");
                return $queryToken;
            }
            
            // Try Cookie second (for subsequent requests)
            if (isset($request->cookie['admin_token'])) {
                $cookieToken = $request->cookie['admin_token'];
                error_log("[AdminAuthMiddleware] Token found in request->cookie, value: " . (empty($cookieToken) ? "EMPTY" : substr($cookieToken, 0, 20) . "..."));
                if (!empty($cookieToken)) {
                    return $cookieToken;
                }
            }
            
            // Debug: log all cookies
            if (isset($request->cookie) && is_array($request->cookie)) {
                error_log("[AdminAuthMiddleware] Request cookies: " . json_encode(array_keys($request->cookie)));
            }
            
            // Debug: log all query parameters
            if (isset($request->get) && is_array($request->get)) {
                error_log("[AdminAuthMiddleware] Request query params: " . json_encode(array_keys($request->get)));
            }
            
            // Try Authorization header
            $headers = $request->header ?? [];
            $authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
            
            if ($authHeader) {
                if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    error_log("[AdminAuthMiddleware] Token found in Authorization header");
                    return $matches[1];
                }
            }
            
            // Try token header
            if (isset($headers['token'])) {
                error_log("[AdminAuthMiddleware] Token found in token header");
                return $headers['token'];
            }
        }
        
        // Fallback: try $_COOKIE
        if (isset($_COOKIE['admin_token'])) {
            error_log("[AdminAuthMiddleware] Token found in \$_COOKIE");
            return $_COOKIE['admin_token'];
        }
        
        // Fallback: try $_GET (for query parameter)
        if (isset($_GET['token'])) {
            error_log("[AdminAuthMiddleware] Token found in \$_GET");
            return $_GET['token'];
        }
        
        error_log("[AdminAuthMiddleware] No token found in any location");
        error_log("[AdminAuthMiddleware] \$_COOKIE keys: " . implode(', ', array_keys($_COOKIE ?? [])));
        if (is_object($request) && isset($request->cookie)) {
            error_log("[AdminAuthMiddleware] Request cookie keys: " . implode(', ', array_keys($request->cookie ?? [])));
        }
        
        return null;
    }
}

