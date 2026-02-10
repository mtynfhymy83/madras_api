<?php

namespace App\Services;

use App\Database\QueryBuilder;

class PermissionService
{
    /**
     * دریافت تمام دسترسی‌ها
     */
    public function getAllPermissions(?string $category = null): array
    {
        $qb = (new QueryBuilder())
            ->table('permissions')
            ->withoutSoftDelete()
            ->where('is_active', '=', true);
            
        if ($category) {
            $qb->where('category', '=', $category);
        }
        
        return $qb->orderBy('category', 'ASC')
                  ->orderBy('name', 'ASC')
                  ->get();
    }
    
    /**
     * دریافت دسترسی‌های یک نقش
     */
    public function getRolePermissions(int $roleId): array
    {
        return (new QueryBuilder())
            ->table('role_permissions as rp')
            ->withoutSoftDelete()
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', '=', $roleId)
            ->where('p.is_active', '=', true)
            ->select('p.id', 'p.name', 'p.display_name', 'p.category')
            ->orderBy('p.category', 'ASC')
            ->orderBy('p.name', 'ASC')
            ->get();
    }
    
    /**
     * دریافت دسترسی‌های یک کاربر (از تمام نقش‌هایش)
     */
    public function getUserPermissions(int $userId): array
    {
        // دریافت تمام permissions از تمام roles کاربر
        $permissions = (new QueryBuilder())
            ->table('user_roles as ur')
            ->withoutSoftDelete()
            ->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ur.user_id', '=', $userId)
            ->where('p.is_active', '=', true)
            ->select('p.name')
            ->get();
            
        // برگرداندن فقط لیست نام‌ها (برای سرعت بیشتر)
        return array_column($permissions, 'name');
    }
    
    /**
     * بررسی اینکه آیا کاربر یک permission خاص را دارد
     */
    public function userHasPermission(int $userId, string $permissionName): bool
    {
        $count = (new QueryBuilder())
            ->table('user_roles as ur')
            ->withoutSoftDelete()
            ->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ur.user_id', '=', $userId)
            ->where('p.name', '=', $permissionName)
            ->where('p.is_active', '=', true)
            ->count();
            
        return $count > 0;
    }
    
    /**
     * بررسی اینکه آیا کاربر super admin است
     */
    public function isSuperAdmin(int $userId): bool
    {
        $count = (new QueryBuilder())
            ->table('user_roles as ur')
            ->withoutSoftDelete()
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', '=', $userId)
            ->where('r.name', '=', 'super_admin')
            ->where('r.is_active', '=', true)
            ->count();
            
        return $count > 0;
    }
    
    /**
     * دریافت دسته‌بندی‌های permissions
     */
    public function getPermissionCategories(): array
    {
        $result = (new QueryBuilder())
            ->table('permissions')
            ->withoutSoftDelete()
            ->where('is_active', '=', true)
            ->select('category')
            ->groupBy('category')
            ->get();
            
        return array_column($result, 'category');
    }
    
    /**
     * دریافت permissions به تفکیک category
     */
    public function getPermissionsGroupedByCategory(): array
    {
        $permissions = $this->getAllPermissions();
        
        $grouped = [];
        foreach ($permissions as $permission) {
            $category = $permission['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $permission;
        }
        
        return $grouped;
    }
}
