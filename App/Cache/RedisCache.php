<?php

namespace App\Cache;

use Exception;

/**
 * Redis Cache Implementation using native \Redis extension
 * Optimized for Swoole - no memory fallback
 * 
 * Note: Requires php-redis extension installed
 * Fallback to PredisCache if extension not available
 */
final class RedisCache implements CacheStoreInterface
{
    private \Redis $redis;
    private string $prefix;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        int $database = 0,
        ?string $password = null,
        string $prefix = ''
    ) {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not installed. Install php-redis or use PredisCache.');
        }

        $this->redis = new \Redis();
        
        // Connect with timeout (افزایش timeout برای Redis remote)
        // برای Redis remote، timeout بیشتر نیاز است
        $timeout = 5.0; // 5 ثانیه برای اتصال
        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new Exception("Failed to connect to Redis at {$host}:{$port}");
        }
        
        // Set read/write timeout برای عملیات بعدی
        $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, 3.0); // 3 ثانیه برای read

        // Authenticate if password provided
        if ($password !== null && $password !== '') {
            if (!$this->redis->auth($password)) {
                throw new Exception('Redis authentication failed');
            }
        }

        // Select database
        $this->redis->select($database);

        // Set options for Swoole compatibility
        $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP); // Use PHP serialize (faster than JSON)
        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $this->redis->setOption(\Redis::OPT_PREFIX, $prefix);

        $this->prefix = $prefix;

        // Test connection
        if (!$this->redis->ping()) {
            throw new Exception('Redis ping failed');
        }
    }

    public function get(string $key)
    {
        try {
            $value = $this->redis->get($key);
            return $value !== false ? $value : null;
        } catch (Exception $e) {
            error_log("Redis get error: " . $e->getMessage());
            return null;
        }
    }

    public function set(string $key, $value, int $ttl): void
    {
        try {
            if ($ttl > 0) {
                $this->redis->setex($key, $ttl, $value);
            } else {
                $this->redis->set($key, $value);
            }
        } catch (Exception $e) {
            error_log("Redis set error: " . $e->getMessage());
            // Don't throw - cache failures shouldn't break the app
        }
    }

    public function delete(string $key): void
    {
        try {
            $this->redis->del($key);
        } catch (Exception $e) {
            error_log("Redis delete error: " . $e->getMessage());
        }
    }

    public function has(string $key): bool
    {
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            error_log("Redis exists error: " . $e->getMessage());
            return false;
        }
    }

    public function clear(): void
    {
        try {
            $this->redis->flushDB();
        } catch (Exception $e) {
            error_log("Redis flushDB error: " . $e->getMessage());
        }
    }

    /**
     * Close connection (important for Swoole)
     */
    public function close(): void
    {
        try {
            $this->redis->close();
        } catch (Exception $e) {
            // Ignore close errors
        }
    }

    /**
     * Get Redis instance (for advanced operations)
     */
    public function getRedis(): \Redis
    {
        return $this->redis;
    }
}
