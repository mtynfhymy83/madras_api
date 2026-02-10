<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\PermissionService;
use App\Middlewares\PermissionMiddleware;
use Swoole\Http\Request;
use Exception;

class PermissionController extends Controller
{
    private PermissionService $permissionService;
    private PermissionMiddleware $permissionMiddleware;
    
    public function __construct()
    {
        $this->permissionService = new PermissionService();
        $this->permissionMiddleware = new PermissionMiddleware();
    }
    
    /**
     * List all permissions.
     */
    public function index(Request $swooleRequest, array $request): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'permissions.manage');
            
            $category = $request['category'] ?? null;
            $permissions = $this->permissionService->getAllPermissions($category);
            
            return $this->sendResponse($permissions, 'Permissions list retrieved successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get permissions grouped by category.
     */
    public function grouped(Request $swooleRequest): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'permissions.manage');
            
            $grouped = $this->permissionService->getPermissionsGroupedByCategory();
            
            return $this->sendResponse($grouped, 'Permissions grouped by category successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get permissions assigned to a role.
     */
    public function getRolePermissions(Request $swooleRequest, int $role_id): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'roles.view');
            
            if ($role_id <= 0) {
                return $this->sendResponse(null, 'Invalid role ID', true, 400);
            }
            
            $permissions = $this->permissionService->getRolePermissions($role_id);
            
            return $this->sendResponse($permissions, 'Role permissions retrieved successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get permissions assigned to a user.
     */
    public function getUserPermissions(Request $swooleRequest, int $user_id): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'users.view');
            
            if ($user_id <= 0) {
                return $this->sendResponse(null, 'Invalid user ID', true, 400);
            }
            
            $permissions = $this->permissionService->getUserPermissions($user_id);
            
            return $this->sendResponse($permissions, 'User permissions retrieved successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
}
