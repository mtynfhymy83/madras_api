<?php

namespace App\Middlewares;

use App\Services\PermissionService;
use App\Exceptions\AccessDeniedException;
use Swoole\Http\Request;
use Swoole\Http\Response;

class PermissionMiddleware
{
    private PermissionService $permissionService;
    
    public function __construct()
    {
        $this->permissionService = new PermissionService();
    }
    
    /**
     * بررسی دسترسی کاربر
     * 
     * @param Request $request
     * @param string|array $requiredPermissions - یک permission یا آرایه‌ای از permissions
     * @param bool $requireAll - اگر true باشد، کاربر باید همه permissions را داشته باشد
     * @throws AccessDeniedException
     */
    public function checkPermission(Request $request, $requiredPermissions, bool $requireAll = false): bool
    {
        // دریافت اطلاعات کاربر از توکن
        $userDetail = $this->getUserDetailFromToken($request);
        
        if (!$userDetail || !isset($userDetail->id)) {
            throw new AccessDeniedException("احراز هویت نشده‌اید", 401);
        }
        
        $userId = (int)$userDetail->id;
        
        // Super Admin: دسترسی به همه چیز
        if ($this->permissionService->isSuperAdmin($userId)) {
            return true;
        }
        
        // اگر permissions از توکن موجود است، از آن استفاده کن (سریعتر)
        $userPermissions = $userDetail->permissions ?? null;
        
        // اگر permissions در توکن نبود، از دیتابیس بگیر
        if ($userPermissions === null) {
            $userPermissions = $this->permissionService->getUserPermissions($userId);
        }
        
        // تبدیل به آرایه اگر string است
        if (is_string($requiredPermissions)) {
            $requiredPermissions = [$requiredPermissions];
        }
        
        // بررسی دسترسی
        if ($requireAll) {
            // کاربر باید همه permissions را داشته باشد
            foreach ($requiredPermissions as $permission) {
                if (!in_array($permission, $userPermissions)) {
                    throw new AccessDeniedException("شما به این بخش دسترسی ندارید (نیاز به: $permission)", 403);
                }
            }
            return true;
        } else {
            // کاربر حداقل یکی از permissions را داشته باشد
            foreach ($requiredPermissions as $permission) {
                if (in_array($permission, $userPermissions)) {
                    return true;
                }
            }
            
            $required = implode(', ', $requiredPermissions);
            throw new AccessDeniedException("شما به این بخش دسترسی ندارید (نیاز به یکی از: $required)", 403);
        }
    }
    
    /**
     * دریافت اطلاعات کاربر از توکن JWT
     */
    private function getUserDetailFromToken(Request $request): ?object
    {
        $headers = $request->header ?? [];
        
        // دریافت توکن
        $authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
        $token = null;
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        if (!$token) {
            $token = $headers['token'] ?? null;
        }
        
        if (!$token) {
            return null;
        }
        
        // Decode JWT
        try {
            $secretKey = $_ENV['JWT_SECRET'] ?? $_ENV['SECRET_KEY'] ?? null;
            $algo = $_ENV['JWT_ALGO'] ?? 'HS256';
            
            if (!$secretKey) {
                return null;
            }
            
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secretKey, $algo));
            return $decoded;
        } catch (\Exception $e) {
            error_log("[PermissionMiddleware] Token decode error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Helper: بررسی سریع یک permission
     */
    public function check(Request $request, string $permission): bool
    {
        try {
            return $this->checkPermission($request, $permission);
        } catch (AccessDeniedException $e) {
            return false;
        }
    }
}
