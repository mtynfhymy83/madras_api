<?php

namespace App\Traits;

use App\Cache\Cache;

trait CacheInvalidationTrait {
    /**
     * Invalidate cache for a specific table
     * 
     * @param string $table Table name
     * @param int|null $id Specific record ID (optional)
     */
    protected function invalidateTableCache(string $table, ?int $id = null): void
    {
        // Clear cache patterns for the table
        $patterns = [
            "table_{$table}_*",  // All queries for this table
            "count_{$table}_*",  // Count queries
        ];

        if ($id !== null) {
            $patterns[] = "table_{$table}_id_{$id}";
            $patterns[] = "count_{$table}_id_{$id}";
        }

        // Note: Redis pattern deletion would require SCAN, which is complex
        // For now, we'll use a simpler approach with specific keys
        // In production, you might want to use a cache tag system
    }

    /**
     * Clear all cache for admin API
     */
    protected function clearAdminCache(): void
    {
        // Clear common admin cache patterns
        Cache::delete('admin_posts_list');
        Cache::delete('admin_users_list');
        Cache::delete('admin_comments_list');
        Cache::delete('admin_settings');
    }

    /**
     * Generate cache key for a query
     * 
     * @param string $table Table name
     * @param array $params Query parameters
     * @return string Cache key
     */
    protected function getCacheKey(string $table, array $params = []): string
    {
        $key = "admin_{$table}_" . md5(serialize($params));
        return $key;
    }
}

