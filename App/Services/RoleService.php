<?php

namespace App\Services;

use App\Database\QueryBuilder;
use Exception;

class RoleService
{
    /**
     * دریافت تمام نقش‌ها
     */
    public function getAllRoles(): array
    {
        return (new QueryBuilder())
            ->table('roles')
            ->withoutSoftDelete()
            ->where('is_active', '=', true)
            ->orderBy('priority', 'DESC')
            ->get();
    }
    
    /**
     * دریافت یک نقش با permissions
     */
    public function getRoleWithPermissions(int $roleId): ?array
    {
        $role = (new QueryBuilder())
            ->table('roles')
            ->withoutSoftDelete()
            ->where('id', '=', $roleId)
            ->first();
            
        if (!$role) {
            return null;
        }
        
        // دریافت permissions این نقش
        $permissions = (new QueryBuilder())
            ->table('role_permissions as rp')
            ->withoutSoftDelete()
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', '=', $roleId)
            ->where('p.is_active', '=', true)
            ->select('p.id', 'p.name', 'p.display_name', 'p.category')
            ->get();
            
        $role['permissions'] = $permissions;
        
        return $role;
    }
    
    /**
     * ایجاد نقش جدید
     */
    public function createRole(array $data): int
    {
        $roleData = [
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        return (new QueryBuilder())
            ->table('roles')
            ->withoutSoftDelete()
            ->insert($roleData);
    }
    
    /**
     * به‌روزرسانی نقش
     */
    public function updateRole(int $roleId, array $data): bool
    {
        $updateData = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        if (isset($data['display_name'])) {
            $updateData['display_name'] = $data['display_name'];
        }
        
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        
        if (isset($data['priority'])) {
            $updateData['priority'] = $data['priority'];
        }
        
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        
        return (new QueryBuilder())
            ->table('roles')
            ->withoutSoftDelete()
            ->where('id', '=', $roleId)
            ->update($updateData);
    }
    
    /**
     * حذف نقش
     */
    public function deleteRole(int $roleId): bool
    {
        // بررسی اینکه نقش system نباشد
        $role = (new QueryBuilder())
            ->table('roles')
            ->withoutSoftDelete()
            ->where('id', '=', $roleId)
            ->first();
            
        if (!$role) {
            throw new Exception('نقش یافت نشد');
        }
        
        // جلوگیری از حذف نقش‌های سیستمی
        if (in_array($role['name'], ['super_admin', 'admin', 'operator', 'user'])) {
            throw new Exception('نقش‌های سیستمی قابل حذف نیستند');
        }
        
        return (new QueryBuilder())
            ->table('roles')
            ->withoutSoftDelete()
            ->where('id', '=', $roleId)
            ->delete();
    }
    
    /**
     * تخصیص permission به role
     */
    public function assignPermissionToRole(int $roleId, int $permissionId): bool
    {
        // بررسی وجود role و permission
        $role = (new QueryBuilder())
            ->table('roles')
            ->withoutSoftDelete()
            ->where('id', '=', $roleId)
            ->first();
            
        if (!$role) {
            throw new Exception('نقش یافت نشد');
        }
        
        $permission = (new QueryBuilder())
            ->table('permissions')
            ->withoutSoftDelete()
            ->where('id', '=', $permissionId)
            ->first();
            
        if (!$permission) {
            throw new Exception('دسترسی یافت نشد');
        }
        
        // بررسی اینکه قبلاً تخصیص داده نشده باشد
        $exists = (new QueryBuilder())
            ->table('role_permissions')
            ->withoutSoftDelete()
            ->where('role_id', '=', $roleId)
            ->where('permission_id', '=', $permissionId)
            ->first();
            
        if ($exists) {
            return true; // Already assigned
        }
        
        return (bool)(new QueryBuilder())
            ->table('role_permissions')
            ->withoutSoftDelete()
            ->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], false);
    }
    
    /**
     * حذف permission از role
     */
    public function removePermissionFromRole(int $roleId, int $permissionId): bool
    {
        return (new QueryBuilder())
            ->table('role_permissions')
            ->withoutSoftDelete()
            ->where('role_id', '=', $roleId)
            ->where('permission_id', '=', $permissionId)
            ->delete();
    }
    
    /**
     * تخصیص role به user
     */
    public function assignRoleToUser(int $userId, int $roleId): bool
    {
        // بررسی وجود user و role
        $user = (new QueryBuilder())
            ->table('users')
            ->where('id', '=', $userId)
            ->first();
            
        if (!$user) {
            throw new Exception('کاربر یافت نشد');
        }
        
        $role = (new QueryBuilder())
            ->table('roles')
            ->withoutSoftDelete()
            ->where('id', '=', $roleId)
            ->first();
            
        if (!$role) {
            throw new Exception('نقش یافت نشد');
        }
        
        // بررسی اینکه قبلاً تخصیص داده نشده باشد
        $exists = (new QueryBuilder())
            ->table('user_roles')
            ->withoutSoftDelete()
            ->where('user_id', '=', $userId)
            ->where('role_id', '=', $roleId)
            ->first();
            
        if ($exists) {
            return true; // Already assigned
        }
        
        return (bool)(new QueryBuilder())
            ->table('user_roles')
            ->withoutSoftDelete()
            ->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], false);
    }
    
    /**
     * حذف role از user
     */
    public function removeRoleFromUser(int $userId, int $roleId): bool
    {
        return (new QueryBuilder())
            ->table('user_roles')
            ->withoutSoftDelete()
            ->where('user_id', '=', $userId)
            ->where('role_id', '=', $roleId)
            ->delete();
    }
    
    /**
     * دریافت نقش‌های یک کاربر
     */
    public function getUserRoles(int $userId): array
    {
        return (new QueryBuilder())
            ->table('user_roles as ur')
            ->withoutSoftDelete()
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', '=', $userId)
            ->where('r.is_active', '=', true)
            ->select('r.id', 'r.name', 'r.display_name', 'r.priority')
            ->orderBy('r.priority', 'DESC')
            ->get();
    }
    
    /**
     * دریافت بالاترین نقش کاربر
     */
    public function getUserHighestRole(int $userId): ?array
    {
        return (new QueryBuilder())
            ->table('user_roles as ur')
            ->withoutSoftDelete()
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', '=', $userId)
            ->where('r.is_active', '=', true)
            ->select('r.id', 'r.name', 'r.display_name', 'r.priority')
            ->orderBy('r.priority', 'DESC')
            ->first();
    }
}
