<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\RoleService;
use App\Services\PermissionService;
use App\Middlewares\PermissionMiddleware;
use Swoole\Http\Request;
use Exception;

class RoleController extends Controller
{
    private RoleService $roleService;
    private PermissionService $permissionService;
    private PermissionMiddleware $permissionMiddleware;
    
    public function __construct()
    {
        $this->roleService = new RoleService();
        $this->permissionService = new PermissionService();
        $this->permissionMiddleware = new PermissionMiddleware();
    }
    
    /**
     * List all roles.
     * Only super admin can access.
     */
    public function index(Request $swooleRequest): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'roles.view');
            
            $roles = $this->roleService->getAllRoles();
            
            return $this->sendResponse($roles, 'Roles list retrieved successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get a single role with its permissions.
     */
    public function show(Request $swooleRequest, int $id): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'roles.view');
            
            if ($id <= 0) {
                return $this->sendResponse(null, 'Invalid role ID', true, 400);
            }
            
            $role = $this->roleService->getRoleWithPermissions($id);
            
            if (!$role) {
                return $this->sendResponse(null, 'Role not found', true, 404);
            }
            
            return $this->sendResponse($role, 'Role retrieved successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Assign a permission to a role.
     */
    public function assignPermission(Request $swooleRequest, array $request): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'permissions.manage');
            
            $roleId = (int)($request['role_id'] ?? 0);
            $permissionId = (int)($request['permission_id'] ?? 0);
            
            if ($roleId <= 0 || $permissionId <= 0) {
                return $this->sendResponse(null, 'Invalid role or permission ID', true, 400);
            }
            
            $this->roleService->assignPermissionToRole($roleId, $permissionId);
            
            return $this->sendResponse(null, 'Permission assigned to role successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Remove a permission from a role.
     */
    public function removePermission(Request $swooleRequest, array $request): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'permissions.manage');
            
            $roleId = (int)($request['role_id'] ?? 0);
            $permissionId = (int)($request['permission_id'] ?? 0);
            
            if ($roleId <= 0 || $permissionId <= 0) {
                return $this->sendResponse(null, 'Invalid role or permission ID', true, 400);
            }
            
            $this->roleService->removePermissionFromRole($roleId, $permissionId);
            
            return $this->sendResponse(null, 'Permission removed from role', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Assign a role to a user.
     */
    public function assignToUser(Request $swooleRequest, array $request): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'users.change_role');
            
            $userId = (int)($request['user_id'] ?? 0);
            $roleId = (int)($request['role_id'] ?? 0);
            
            if ($userId <= 0 || $roleId <= 0) {
                return $this->sendResponse(null, 'Invalid user or role ID', true, 400);
            }
            
            $this->roleService->assignRoleToUser($userId, $roleId);
            
            return $this->sendResponse(null, 'Role assigned to user successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Remove a role from a user.
     */
    public function removeFromUser(Request $swooleRequest, array $request): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'users.change_role');
            
            $userId = (int)($request['user_id'] ?? 0);
            $roleId = (int)($request['role_id'] ?? 0);
            
            if ($userId <= 0 || $roleId <= 0) {
                return $this->sendResponse(null, 'Invalid user or role ID', true, 400);
            }
            
            $this->roleService->removeRoleFromUser($userId, $roleId);
            
            return $this->sendResponse(null, 'Role removed from user', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get all roles assigned to a user.
     */
    public function getUserRoles(Request $swooleRequest, int $user_id): array
    {
        try {
            $this->permissionMiddleware->checkPermission($swooleRequest, 'users.view');
            
            if ($user_id <= 0) {
                return $this->sendResponse(null, 'Invalid user ID', true, 400);
            }
            
            $roles = $this->roleService->getUserRoles($user_id);
            
            return $this->sendResponse($roles, 'User roles retrieved successfully', false, 200);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, $e->getCode() ?: 500);
        }
    }
}
