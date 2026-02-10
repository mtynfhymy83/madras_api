<?php

namespace App\Cache;

use Exception;

/**
 * Cache Facade - Singleton wrapper for CacheStoreInterface
 * Auto-detects best available driver (Redis extension > Predis)
 * No memory fallback - fails gracefully if Redis unavailable
 */
class Cache
{
    private static ?CacheStoreInterface $store = null;
    private static bool $enabled = true;
    private static int $defaultTtl = 600; // 10 minutes

    /**
     * Initialize cache store
     */
    public static function init(): void
    {
        if (self::$store !== null) {
            return;
        }

        // Check if cache is enabled
        self::$enabled = (($_ENV['CACHE_ENABLED'] ?? 'true') === 'true');
        if (!self::$enabled) {
            return;
        }

        // Load config
        $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
        $database = (int)($_ENV['REDIS_DB'] ?? 0);
        $password = $_ENV['REDIS_PASSWORD'] ?? null;
        $prefix = $_ENV['CACHE_PREFIX'] ?? '';
        self::$defaultTtl = (int)($_ENV['CACHE_TTL'] ?? self::$defaultTtl);

        // Try Redis extension first (faster)
        if (extension_loaded('redis')) {
            try {
                self::$store = new RedisCache($host, $port, $database, $password, $prefix);
                return;
            } catch (Exception $e) {
                error_log("RedisCache initialization failed: " . $e->getMessage());
            }
        }

        // Fallback to Predis
        try {
            self::$store = new PredisCache($host, $port, $database, $password, $prefix);
        } catch (Exception $e) {
            error_log("Cache initialization failed: " . $e->getMessage());
            self::$store = null;
        }
    }

    /**
     * Get value from cache
     */
    public static function get(string $key)
    {
        if (!self::$enabled || self::$store === null) {
            return null;
        }

        try {
            return self::$store->get($key);
        } catch (Exception $e) {
            error_log("Cache get error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set value in cache
     */
    public static function set(string $key, $value, ?int $ttl = null): void
    {
        if (!self::$enabled || self::$store === null) {
            return;
        }

        try {
            self::$store->set($key, $value, $ttl ?? self::$defaultTtl);
        } catch (Exception $e) {
            error_log("Cache set error: " . $e->getMessage());
        }
    }

    /**
     * Delete value from cache
     */
    public static function delete(string $key): void
    {
        if (!self::$enabled || self::$store === null) {
            return;
        }

        try {
            self::$store->delete($key);
        } catch (Exception $e) {
            error_log("Cache delete error: " . $e->getMessage());
        }
    }

    /**
     * Check if key exists
     */
    public static function has(string $key): bool
    {
        if (!self::$enabled || self::$store === null) {
            return false;
        }

        try {
            return self::$store->has($key);
        } catch (Exception $e) {
            error_log("Cache has error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all cache
     */
    public static function clear(): void
    {
        if (!self::$enabled || self::$store === null) {
            return;
        }

        try {
            self::$store->clear();
        } catch (Exception $e) {
            error_log("Cache clear error: " . $e->getMessage());
        }
    }

    /**
     * Get cache statistics
     */
    public static function getStats(): array
    {
        if (!self::$enabled || self::$store === null) {
            return ['enabled' => false];
        }

        $driver = self::$store instanceof RedisCache ? 'redis-extension' : 'predis';
        
        return [
            'enabled' => true,
            'driver' => $driver,
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
        ];
    }

    /**
     * Set store instance (for testing)
     */
    public static function setStore(CacheStoreInterface $store): void
    {
        self::$store = $store;
    }
}
