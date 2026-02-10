<?php

namespace App\Services;

use App\Database\QueryBuilder;
use App\Database\DB;
use App\DTOs\Admin\Category\CreateCategoryRequestDTO;
use App\DTOs\Admin\Category\UpdateCategoryRequestDTO;
use RuntimeException;

class CategoryService
{
    private function generateSlug(string $title, string $type): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $title = str_replace($persian, $english, $title);
        $title = str_replace($arabic, $english, $title);
        $title = mb_strtolower($title, 'UTF-8');
        $title = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $title);
        $title = preg_replace('/[\s-]+/', '-', $title);
        $title = trim($title, '-');

        if ($title === '') {
            $title = 'category-' . time();
        }

        $baseSlug = $title;
        $counter = 1;
        while ($this->slugExists($title, $type)) {
            $title = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $title;
    }

    private function slugExists(string $slug, string $type): bool
    {
        $qb = new QueryBuilder();
        $category = $qb->table('categories')
            ->withoutSoftDelete()
            ->where('slug', '=', $slug)
            ->where('type', '=', $type)
            ->first();

        return $category !== null;
    }

    private function slugExistsExcept(string $slug, string $type, int $exceptId): bool
    {
        $qb = new QueryBuilder();
        $category = $qb->table('categories')
            ->withoutSoftDelete()
            ->where('slug', '=', $slug)
            ->where('type', '=', $type)
            ->where('id', '!=', $exceptId)
            ->first();

        return $category !== null;
    }

    public function createCategory(CreateCategoryRequestDTO $dto): array
    {
        $type = $dto->type ?? 'book';
        $slug = $dto->slug ?? $this->generateSlug($dto->title, $type);

        $parent = null;
        if (!empty($dto->parentId)) {
            $parent = (new QueryBuilder())
                ->table('categories')
                ->withoutSoftDelete()
                ->where('id', '=', $dto->parentId)
                ->first();

            if (!$parent) {
                throw new RuntimeException('دسته‌بندی والد یافت نشد', 400);
            }
        }

        $depth = $parent ? (int)($parent['depth'] ?? 0) + 1 : 0;

        $data = [
            'title' => $dto->title,
            'slug' => $slug,
            'parent_id' => $dto->parentId,
            'icon' => $dto->icon,
            'type' => $type,
            'is_active' => $dto->isActive ?? true,
            'sort_order' => $dto->sortOrder ?? 0,
            'depth' => $depth,
        ];

        $qb = new QueryBuilder();
        $categoryId = (int)$qb->table('categories')->withoutSoftDelete()->insert($data);

        $fullPath = null;
        if ($parent) {
            $parentPath = $parent['full_path'] ?? null;
            if (!empty($parentPath)) {
                $fullPath = $parentPath . '/' . $categoryId;
            } else {
                $fullPath = $parent['id'] . '/' . $categoryId;
            }
        } else {
            $fullPath = (string)$categoryId;
        }

        (new QueryBuilder())
            ->table('categories')
            ->withoutSoftDelete()
            ->where('id', '=', $categoryId)
            ->update(['full_path' => $fullPath]);

        $category = (new QueryBuilder())
            ->table('categories')
            ->withoutSoftDelete()
            ->where('id', '=', $categoryId)
            ->first();

        return [
            'id' => (int)$category['id'],
            'title' => $category['title'],
            'slug' => $category['slug'],
            'parent_id' => isset($category['parent_id']) ? (int)$category['parent_id'] : null,
            'icon' => $category['icon'] ?? null,
            'type' => $category['type'],
            'is_active' => (bool)$category['is_active'],
            'sort_order' => (int)($category['sort_order'] ?? 0),
            'depth' => (int)($category['depth'] ?? 0),
            'full_path' => $category['full_path'] ?? null,
        ];
    }

    public function getCategoriesList(array $params = []): array
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);
        $search = $params['search'] ?? null;
        $parentId = $params['parent_id'] ?? null;
        $type = $params['type'] ?? null;
        $isActive = $params['is_active'] ?? null;

        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        $qb = new QueryBuilder();
        $qb->table('categories')->withoutSoftDelete();

        if (!empty($search)) {
            $qb->where('title', 'ILIKE', "%{$search}%");
        }
        if ($parentId !== null && $parentId !== '') {
            $qb->where('parent_id', '=', (int)$parentId);
        }
        if (!empty($type)) {
            $qb->where('type', '=', $type);
        }
        if ($isActive !== null && $isActive !== '') {
            $qb->where('is_active', '=', (bool)$isActive);
        }

        $countQb = new QueryBuilder();
        $countQb->table('categories')->withoutSoftDelete();
        if (!empty($search)) {
            $countQb->where('title', 'ILIKE', "%{$search}%");
        }
        if ($parentId !== null && $parentId !== '') {
            $countQb->where('parent_id', '=', (int)$parentId);
        }
        if (!empty($type)) {
            $countQb->where('type', '=', $type);
        }
        if ($isActive !== null && $isActive !== '') {
            $countQb->where('is_active', '=', (bool)$isActive);
        }

        $total = $countQb->count();

        $qb->orderBy('sort_order', 'ASC')
            ->limit($limit)
            ->offset(($page - 1) * $limit);

        $rows = $qb->get();

        $items = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'parent_id' => isset($row['parent_id']) ? (int)$row['parent_id'] : null,
                'icon' => $row['icon'] ?? null,
                'type' => $row['type'] ?? null,
                'is_active' => (bool)($row['is_active'] ?? false),
                'sort_order' => (int)($row['sort_order'] ?? 0),
                'depth' => (int)($row['depth'] ?? 0),
                'full_path' => $row['full_path'] ?? null,
            ];
        }, $rows);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    public function updateCategory(int $id, UpdateCategoryRequestDTO $dto): array
    {
        $qb = new QueryBuilder();
        $current = $qb->table('categories')
            ->withoutSoftDelete()
            ->where('id', '=', $id)
            ->first();

        if (!$current) {
            throw new RuntimeException('دسته‌بندی یافت نشد', 404);
        }

        $newType = $dto->type ?? $current['type'] ?? 'book';
        $newSlug = $dto->slug ?? $current['slug'];

        if ($newSlug !== null && $newSlug !== '' && ($newSlug !== $current['slug'] || $newType !== $current['type'])) {
            if ($this->slugExistsExcept($newSlug, $newType, $id)) {
                throw new RuntimeException('اسلاگ تکراری است', 400);
            }
        }

        $newParentId = $dto->parentId;
        if ($newParentId !== null && $newParentId === $id) {
            throw new RuntimeException('دسته‌بندی نمی‌تواند والد خودش باشد', 400);
        }

        $parent = null;
        if ($dto->parentIdProvided) {
            if ($newParentId === null || $newParentId === 0) {
                $parent = null;
            } else {
                $parent = (new QueryBuilder())
                    ->table('categories')
                    ->withoutSoftDelete()
                    ->where('id', '=', $newParentId)
                    ->first();

                if (!$parent) {
                    throw new RuntimeException('دسته‌بندی والد یافت نشد', 400);
                }

                $currentPath = (string)($current['full_path'] ?? '');
                $parentPath = (string)($parent['full_path'] ?? '');
                if ($currentPath !== '' && $parentPath !== '' && str_starts_with($parentPath, $currentPath)) {
                    throw new RuntimeException('انتخاب والد باعث ایجاد چرخه می‌شود', 400);
                }
            }
        }

        $data = [];
        if ($dto->title !== null) $data['title'] = $dto->title;
        if ($dto->slug !== null) $data['slug'] = $newSlug;
        if ($dto->icon !== null) $data['icon'] = $dto->icon;
        if ($dto->type !== null) $data['type'] = $newType;
        if ($dto->isActive !== null) $data['is_active'] = $dto->isActive;
        if ($dto->sortOrder !== null) $data['sort_order'] = $dto->sortOrder;
        if ($dto->parentIdProvided) {
            $data['parent_id'] = $newParentId ?: null;
        }

        $oldPath = (string)($current['full_path'] ?? '');
        $oldDepth = (int)($current['depth'] ?? 0);
        $newDepth = $oldDepth;
        $newPath = $oldPath;

        if ($dto->parentIdProvided) {
            $newDepth = $parent ? (int)($parent['depth'] ?? 0) + 1 : 0;
            $data['depth'] = $newDepth;
            if ($parent) {
                $parentPath = $parent['full_path'] ?? null;
                $newPath = !empty($parentPath) ? ($parentPath . '/' . $id) : ($parent['id'] . '/' . $id);
            } else {
                $newPath = (string)$id;
            }
            $data['full_path'] = $newPath;
        }

        if (!empty($data)) {
            (new QueryBuilder())
                ->table('categories')
                ->withoutSoftDelete()
                ->where('id', '=', $id)
                ->update($data);
        }

        if ($oldPath !== '' && $newPath !== '' && $oldPath !== $newPath) {
            $delta = $newDepth - $oldDepth;
            $sql = "
                UPDATE categories
                SET
                    full_path = regexp_replace(full_path, '^' || ?, ?, 'g'),
                    depth = depth + ?
                WHERE full_path LIKE ? || '/%'
            ";
            DB::execute($sql, [$oldPath, $newPath, $delta, $oldPath]);
        }

        $updated = (new QueryBuilder())
            ->table('categories')
            ->withoutSoftDelete()
            ->where('id', '=', $id)
            ->first();

        return [
            'id' => (int)$updated['id'],
            'title' => $updated['title'],
            'slug' => $updated['slug'],
            'parent_id' => isset($updated['parent_id']) ? (int)$updated['parent_id'] : null,
            'icon' => $updated['icon'] ?? null,
            'type' => $updated['type'],
            'is_active' => (bool)$updated['is_active'],
            'sort_order' => (int)($updated['sort_order'] ?? 0),
            'depth' => (int)($updated['depth'] ?? 0),
            'full_path' => $updated['full_path'] ?? null,
        ];
    }

    public function deleteCategory(int $id): void
    {
        $exists = (new QueryBuilder())
            ->table('categories')
            ->withoutSoftDelete()
            ->where('id', '=', $id)
            ->first();

        if (!$exists) {
            throw new RuntimeException('دسته‌بندی یافت نشد', 404);
        }

        $child = (new QueryBuilder())
            ->table('categories')
            ->withoutSoftDelete()
            ->where('parent_id', '=', $id)
            ->first();

        if ($child) {
            throw new RuntimeException('ابتدا زیردسته‌ها را حذف یا منتقل کنید', 400);
        }

        (new QueryBuilder())
            ->table('categories')
            ->withoutSoftDelete()
            ->where('id', '=', $id)
            ->forceDelete();
    }
}
