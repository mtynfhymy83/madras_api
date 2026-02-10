<?php

namespace App\Middlewares;

use App\Traits\ResponseTrait;
use App\Exceptions\AccessDeniedException;

class CheckAccessMiddleware
{
    use ResponseTrait;

    public function checkAccess($accessed, $request = null, $in = true){
        $token = null;
        $userDetail = null;
        
        // Try to get token from Swoole request if available
        if ($request && is_object($request)) {
            // Check if it's a Swoole Request object (has header property)
            if (property_exists($request, 'header') || isset($request->header)) {
                $headers = $request->header ?? [];
                
                // Swoole headers are usually lowercase, check multiple variations
                $authHeader = $headers['authorization'] 
                           ?? $headers['Authorization'] 
                           ?? $headers['http_authorization'] 
                           ?? null;
                
                // Extract Bearer token
                if ($authHeader) {
                    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                        $token = $matches[1];
                    } elseif (stripos($authHeader, 'Bearer ') === 0) {
                        $token = trim(substr($authHeader, 7));
                    }
                }
                
                // Also check for token header directly
                if (!$token) {
                    $token = $headers['token'] ?? $headers['Token'] ?? null;
                }
                
                // Set $_SERVER for helper functions (getPostDataInput, getTokenFromRequest)
                foreach ($headers as $key => $value) {
                    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
                    $_SERVER[$serverKey] = $value;
                }
                // Also set Authorization directly (common format)
                if (isset($headers['authorization'])) {
                    $_SERVER['HTTP_AUTHORIZATION'] = $headers['authorization'];
                }
            }
            
            // Also check query parameters
            if (!$token && (property_exists($request, 'get') || isset($request->get))) {
                $token = $request->get['token'] ?? null;
            }
        }
        
        // Decode token if we have it
        if ($token) {
            $secretKey = $_ENV['JWT_SECRET'] ?? $_ENV['SECRET_KEY'] ?? null;
            $algo = $_ENV['JWT_ALGO'] ?? 'HS256';
            
            if ($secretKey) {
                try {
                    $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secretKey, $algo));
                    if ($decoded) {
                        $userDetail = $decoded;
                    }
                } catch (\Exception $e) {
                    error_log("[CheckAccessMiddleware] Token decode error: " . $e->getMessage());
                    // Token invalid, continue to try other methods
                }
            }
        }
        
        // Fallback: try getPostDataInput which handles token extraction
        if (!$userDetail) {
            // Set $_SERVER for getTokenFromRequest if in Swoole context
            if ($request && is_object($request) && isset($request->header)) {
                // Temporarily set headers in $_SERVER for helper functions
                foreach ($request->header as $key => $value) {
                    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
                    $_SERVER[$serverKey] = $value;
                }
                // Also set Authorization directly
                if (isset($request->header['authorization'])) {
                    $_SERVER['HTTP_AUTHORIZATION'] = $request->header['authorization'];
                }
            }
            
            $data = getPostDataInput();
            if ($data && isset($data->user_detail)) {
                $userDetail = $data->user_detail;
                if (!$token) {
                    $request_token = getTokenFromRequest();
                    $token = $request_token->headers ?? $request_token->query ?? $request_token->body ?? null;
                }
            }
        }

        if(!$userDetail || !$token){
            throw new AccessDeniedException("توکن شما معتبر نیست!", HTTP_Forbidden);
        }

        // Get role from token, check both role and level
        $userRole = $userDetail->role ?? null;
        $userLevel = $userDetail->level ?? null;
        
        // If level = 2, treat as admin
        if ($userLevel == '2' || $userLevel == 2) {
            $userRole = 'admin';
        } elseif (!$userRole && ($userLevel == '1' || $userLevel == 1)) {
            $userRole = 'guest';
        }

        $access = in_array($userRole, $accessed);
        if(!$in) $access = !in_array($userRole, $accessed);

        if(!$access){
            throw new AccessDeniedException("شما دسترسی این کار رو ندارید!", HTTP_Forbidden);
        }
        return true;
    }
}
