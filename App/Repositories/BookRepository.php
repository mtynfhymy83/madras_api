<?php

namespace App\Repositories;

use App\Database\QueryBuilder;
use App\Cache\Cache;

class BookRepository
{
    public function getPaginatedWithStats(array $filters, int $page, int $perPage): array
    {
        $qb = (new QueryBuilder())->table('products');
    
        
        $this->applyProductFilters($qb, $filters);
    
        
        $countQb = clone $qb;
        $total = $countQb->count();
    
   
        $offset = ($page - 1) * $perPage;
        $data = $qb->select([
                'products.id', 'products.title', 'products.slug', 'products.status', 'products.price',
                'products.price_with_discount', 'products.cover_image', 'products.view_count',
                'products.sale_count', 'products.rate_avg', 'products.created_at',
                'categories.title as category_title',
                'publishers.title as publisher_title',
                '(SELECT p.full_name FROM product_contributors pc 
                  INNER JOIN persons p ON p.id = pc.person_id 
                  WHERE pc.product_id = products.id AND pc.role = \'author\' LIMIT 1) as author_name',
            ])
            ->join('categories', 'categories.id', '=', 'products.category_id', 'LEFT')
            ->join('publishers', 'publishers.id', '=', 'products.publisher_id', 'LEFT')
            ->orderBy('products.' . ($filters['sort'] ?? 'id'), strtoupper($filters['order'] ?? 'DESC'))
            ->limit($perPage)
            ->offset($offset)
            ->get();
    
        return [
            'data' => $data,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => ceil($total / $perPage),
                'from'         => $offset + 1,
                'to'           => $offset + count($data)
            ]
        ];
    }
    
    
    private function applyProductFilters($qb, array $filters): void
    {
        $qb->where('products.type', '=', 'book')
           ->where('products.deleted_at', 'IS', null);
    
        if (!empty($filters['search'])) {
            $qb->where('products.title', 'LIKE', "%{$filters['search']}%");
        }
    
        if (!empty($filters['category_id'])) {
            $qb->where('products.category_id', '=', $filters['category_id']);
        }
    
        if (!empty($filters['publisher_id'])) {
            $qb->where('products.publisher_id', '=', $filters['publisher_id']);
        }
    
        if (isset($filters['status']) && $filters['status'] !== '') {
            $qb->where('products.status', '=', $filters['status']);
        }
    }

    public function getAuthorsForProduct(int $productId): array
    {
        return (new QueryBuilder())
            ->table('product_contributors as pc')
            ->withoutSoftDelete() // product_contributors doesn't have deleted_at column
            ->select(['p.id', 'p.full_name', 'pc.role'])
            ->join('persons as p', 'p.id', '=', 'pc.person_id')
            ->where('pc.product_id', '=', $productId)
            ->where('pc.role', '=', 'author')
            ->get();
    }

    public function getDownloadCount(int $productId): int
    {
        
        return 0;
    }

    public function getShareCount(int $productId): int
    {
        
        return 0;
    }

    public function create(array $data): int
    {
        $qb = (new QueryBuilder())->table('products');
        
        // Ensure fields don't exceed database limits
        $insertData = [
            'type' => 'book',
            'title' => substr($data['title'], 0, 500),
            'slug' => substr($data['slug'], 0, 500),
            'status' => $data['status'],
            'price' => $data['price'],
            'price_with_discount' => $data['price'],
            'publisher_id' => $data['publisher_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'cover_image' => isset($data['cover_image']) ? substr($data['cover_image'], 0, 1000) : null,
            'description' => $data['description'] ?? null,
            'attributes' => json_encode($data['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
        ];
        
        // Debug log to find which field is too long
        foreach ($insertData as $key => $value) {
            if (is_string($value) && strlen($value) > 1000) {
                error_log("WARNING: Field '$key' is " . strlen($value) . " chars (truncated or will fail)");
            }
        }
        
        $productId = $qb->insert($insertData);

        return (int)$productId;
    }

    public function attachAuthor(int $productId, int $authorId): bool
    {
        $exists = (new QueryBuilder())
            ->table('product_contributors')
            ->withoutSoftDelete() // product_contributors doesn't have deleted_at column
            ->where('product_id', '=', $productId)
            ->where('person_id', '=', $authorId)
            ->where('role', '=', 'author')
            ->first();

        if ($exists) {
            return true;
        }

        return (bool)(new QueryBuilder())
            ->table('product_contributors')
            ->withoutSoftDelete() // product_contributors doesn't have deleted_at column
            ->insert([
                'product_id' => $productId,
                'person_id' => $authorId,
                'role' => 'author'
            ], false); // Don't return id - this table doesn't have id column
    }

    public function attachCategories(int $productId, array $categoryIds): void
    {
        // For now, we only support one category per product
        // If multiple categories needed, we'd need a pivot table
        if (!empty($categoryIds)) {
            $qb = (new QueryBuilder())->table('products');
            $qb->where('id', '=', $productId)->update([
                'category_id' => $categoryIds[0]
            ]);
        }
    }

    public function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = mb_strtolower(trim($title));
        $slug = preg_replace('/\s+/u', '-', $slug);
        $slug = preg_replace('/[^\p{L}\p{N}\-]/u', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        if (mb_strlen($slug) > 200) {
            $slug = mb_substr($slug, 0, 200);
        }
        
        if (empty($slug)) {
            $slug = 'book-' . time();
        }
        
        // Check uniqueness
        $qb = (new QueryBuilder())->table('products');
        $qb->where('slug', '=', $slug);
        if ($excludeId) {
            $qb->where('id', '!=', $excludeId);
        }
        $existing = $qb->first();
        
        if ($existing) {
            $slug .= '-' . time();
        }
        
        return $slug;
    }

    /**
     * Get single book details - zero JOINs; uses products.view_data (denormalized cache).
     * view_data is kept in sync by DB triggers (product_contributors, persons, categories, publishers).
     * Cached in Redis for 10 minutes.
     */
    public function getBookDetails(int $id, ?int $userId = null): ?array
    {
        $cacheKey = "book:details:{$id}";
        $base = null;

        $cached = Cache::get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            $base = $cached;
        } else {
            $sql = "
                SELECT 
                    id, title, slug, description, cover_image,
                    price, price_with_discount, rate_avg, rate_count,
                    attributes, view_data, created_at, updated_at
                FROM products 
                WHERE id = ? AND type = 'book' AND deleted_at IS NULL
            ";
            $row = \App\Database\DB::fetch($sql, [$id]);

            if (!$row) {
                return null;
            }

            $viewData = json_decode($row['view_data'] ?? '{}', true) ?: [];

            $attr = json_decode($row['attributes'] ?? '{}', true) ?: [];
            $base = $this->buildResultFromRowAndViewData($row, $attr, $viewData);
            unset($base['permission']);
            Cache::set($cacheKey, $base, 600);
        }

        // Cache permission جداگانه برای کاهش query های تکراری
        if ($userId) {
            $permissionCacheKey = "book:permission:{$id}:{$userId}";
            $cachedPermission = Cache::get($permissionCacheKey);
            if ($cachedPermission !== null) {
                $base['permission'] = (bool)$cachedPermission;
            } else {
                $base['permission'] = $this->userHasBookAccess($userId, $id);
                // Cache permission برای 5 دقیقه (کمتر از book details چون ممکن است تغییر کند)
                Cache::set($permissionCacheKey, $base['permission'] ? 1 : 0, 300);
            }
        } else {
            $base['permission'] = $this->isFreeBook($base);
        }

        return $base;
    }

    /**
     * برای خرید: فقط یک ردیف محصول (کتاب) با قیمت‌ها برمی‌گرداند. بدون اعتبارسنجی.
     */
    public function getProductRowForPurchase(int $bookId): ?array
    {
        $sql = "SELECT id, price, price_with_discount FROM products WHERE id = ? AND type = 'book'";
        $row = \App\Database\DB::fetch($sql, [$bookId]);
        return $row ?: null;
    }

    /**
     * Check if user has access to a book (fast existence check).
     * Rules:
     * - Free book (price or price_with_discount <= 0)
     * - Purchased (user_library)
     * - Active subscription for book category when has_membership is true in attributes
     */
    public function userHasBookAccess(int $userId, int $bookId): bool
    {
        $subscriptionTableExists = $this->hasTable('user_subscriptions');

        $subscriptionJoin = '';
        $subscriptionCondition = '';
        $params = [$userId];

        if ($subscriptionTableExists) {
            $subscriptionJoin = "
            LEFT JOIN user_subscriptions us
                ON us.user_id = ?
                AND us.category_id = p.category_id
                AND us.is_active = true
                AND us.deleted_at IS NULL
                AND us.expires_at > NOW()
            ";
            $subscriptionCondition = "
                 OR (
                        COALESCE((p.attributes->>'has_membership')::boolean, false) = true
                    AND us.id IS NOT NULL
                 )
            ";
            $params[] = $userId;
        }

        $params[] = $bookId;

        $sql = "
            SELECT 1
            FROM products p
            LEFT JOIN user_library ul
                ON ul.product_id = p.id AND ul.user_id = ?
            {$subscriptionJoin}
            WHERE p.id = ?
              AND p.type = 'book'
              AND p.deleted_at IS NULL
              AND (
                    COALESCE(p.price_with_discount, p.price, 0) <= 0
                 OR ul.user_id IS NOT NULL
                 {$subscriptionCondition}
              )
            LIMIT 1
        ";

        $row = \App\Database\DB::fetch($sql, $params);

        return (bool)$row;
    }

    private function hasTable(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $row = \App\Database\DB::fetch("SELECT to_regclass(?) as t", [$table]);
        $cache[$table] = !empty($row['t']);
        return $cache[$table];
    }

    private function buildResultFromRowAndViewData(array $row, array $attr, array $viewData): array
    {
        $authors = $viewData['authors'] ?? '';
        $translators = $viewData['translators'] ?? '';

        return [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'cover_image' => $row['cover_image'],
            'price' => (int)($row['price'] ?? 0),
            'price_with_discount' => isset($row['price_with_discount']) && $row['price_with_discount'] !== null
                ? (int)$row['price_with_discount'] : null,
            'rating' => [
                'avg' => round((float)($row['rate_avg'] ?? 0), 1),
                'count' => (int)($row['rate_count'] ?? 0),
            ],
            'category' => $viewData['category'] ?? null,
            'publisher' => $viewData['publisher'] ?? null,
            'authors' => $authors !== '' ? $authors : null,
            'translators' => $translators !== '' ? $translators : null,
            'features' => $this->mapFeatures($attr),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private function isFreeBook(array $row): bool
    {
        $price = $row['price_with_discount'] ?? $row['price'] ?? 0;
        return (int)$price <= 0;
    }

    /**
     * Get user's library books (free + purchased)
     */
    public function getUserLibraryBooks(int $userId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $countSql = "
            SELECT COUNT(*) as total
            FROM products p
            INNER JOIN user_library ul
                ON ul.product_id = p.id AND ul.user_id = ?
            WHERE p.type = 'book'
              AND p.deleted_at IS NULL
        ";
        $countRow = \App\Database\DB::fetch($countSql, [$userId]);
        $total = (int)($countRow['total'] ?? 0);

        $sql = "
            SELECT
                p.id, p.title, p.slug, p.cover_image,
                p.price, p.price_with_discount,
                p.attributes, p.view_data, p.created_at, p.updated_at
            FROM products p
            INNER JOIN user_library ul
                ON ul.product_id = p.id AND ul.user_id = ?
            WHERE p.type = 'book'
              AND p.deleted_at IS NULL
            ORDER BY p.updated_at DESC, p.id DESC
            LIMIT ? OFFSET ?
        ";

        $rows = \App\Database\DB::fetchAll($sql, [$userId, $perPage, $offset]);
        $items = [];
        foreach ($rows as $row) {
            $attr = json_decode($row['attributes'] ?? '{}', true) ?: [];
            $viewData = json_decode($row['view_data'] ?? '{}', true) ?: [];
            $items[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'cover_image' => $row['cover_image'],
                'price' => (int)($row['price'] ?? 0),
                'price_with_discount' => isset($row['price_with_discount']) && $row['price_with_discount'] !== null
                    ? (int)$row['price_with_discount'] : null,
                'category' => $viewData['category'] ?? null,
                'authors' => $viewData['authors'] ?? null,
                'translators' => $viewData['translators'] ?? null,
                'features' => $this->mapFeatures($attr),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'permission' => true,
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Invalidate book details cache (call when book is updated)
     */
    public function invalidateBookCache(int $id): void
    {
        Cache::delete("book:details:{$id}");
    }

    /**
     * Rebuild view_data for one book (deprecated - triggers handle this automatically)
     * @deprecated This method is no longer used. Triggers automatically update view_data.
     */
    public function refreshDisplayInfo(int $id): void
    {
        // Deprecated - triggers handle view_data updates automatically
    }

    /**
     * Map attributes to features array for API response
     */
    private function mapFeatures(array $attr): array
    {
        return [
            'page_count' => (int)($attr['pages'] ?? 0),
            'has_sound' => (bool)($attr['has_sound'] ?? false),
            'has_description' => (bool)($attr['has_description'] ?? false),
            'has_test' => (bool)($attr['has_test'] ?? false),
            'has_tashrihi' => (bool)($attr['has_tashrihi'] ?? false),
            'has_translation' => (bool)($attr['has_translation'] ?? false),
            'has_membership' => (bool)($attr['has_membership'] ?? false),
        ];
    }

    /**
     * Get books list for public API (lightweight, fast)
     */
    public function getBooksList(array $filters, int $page, int $perPage): array
    {
        // Build WHERE conditions
        $where = ["p.type = 'book'", "p.deleted_at IS NULL", "p.status = 1"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = "p.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['publisher_id'])) {
            $where[] = "p.publisher_id = ?";
            $params[] = (int)$filters['publisher_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "p.title ILIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Sort
        $sortCol = match($filters['sort'] ?? 'newest') {
            'popular' => 'p.view_count DESC',
            'rating' => 'p.rate_avg DESC',
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            default => 'p.id DESC',
        };

        // Single query for data
        $sql = "
            SELECT 
                p.id, p.title, p.slug, p.cover_image, p.price, p.price_with_discount,
                p.rate_avg, p.rate_count, p.attributes,
                c.title as category_title,
                (
                    SELECT per.full_name
                    FROM product_contributors pc
                    JOIN persons per ON per.id = pc.person_id
                    WHERE pc.product_id = p.id AND pc.role = 'author'
                    LIMIT 1
                ) as author_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE $whereClause
            ORDER BY $sortCol
            LIMIT ? OFFSET ?
        ";

        $dataParams = array_merge($params, [$perPage, $offset]);
        $rows = \App\Database\DB::fetchAll($sql, $dataParams);

        // Count query
        $countSql = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
        $countRow = \App\Database\DB::fetch($countSql, $params);
        $total = (int)($countRow['total'] ?? 0);

        // Transform rows
        $items = array_map(function ($row) {
            $attributes = json_decode($row['attributes'] ?? '{}', true) ?: [];
            return [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'cover_image' => $row['cover_image'],
                'price' => (int)($row['price'] ?? 0),
                'price_with_discount' => $row['price_with_discount'] ? (int)$row['price_with_discount'] : null,
                'rating' => round((float)($row['rate_avg'] ?? 0), 1),
                'rating_count' => (int)($row['rate_count'] ?? 0),
                'author' => $row['author_name'],
                'category' => $row['category_title'],
                'page_count' => (int)($attributes['pages'] ?? 0),
            ];
        }, $rows);

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }
}
