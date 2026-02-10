<?php

namespace App\Database;

use Predis\Client;
use Exception;

class Cache {
    private static $redis = null;
    private static $fallbackCache = [];
    private static $ttl = 300; // 5 minutes default TTL
    private static $useRedis = true;
    private static $enabled = null; // Cache enabled/disabled flag (null = not checked yet)
    private static $redisConfig = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'password' => null,
        'prefix' => ''
    ];

    // Check if cache is enabled
    private static function isEnabled(): bool {
        if (self::$enabled === null) {
            self::$enabled = (($_ENV['CACHE_ENABLED'] ?? 'true') === 'true');
        }
        return self::$enabled;
    }
    
    // Initialize Redis connection
    private static function initRedis() {
        // Check if cache is enabled
        if (!self::isEnabled()) {
            return; // Cache is disabled, skip initialization
        }
        
        if (self::$redis === null) {
            try {
                // Load Redis config from environment if available
                self::$useRedis = (($_ENV['CACHE_DRIVER'] ?? 'redis') === 'redis');
                self::$ttl = (int)($_ENV['CACHE_TTL'] ?? self::$ttl);
                self::$redisConfig['prefix'] = $_ENV['CACHE_PREFIX'] ?? '';

                if (isset($_ENV['REDIS_HOST'])) {
                    self::$redisConfig['host'] = $_ENV['REDIS_HOST'];
                }
                if (isset($_ENV['REDIS_PORT'])) {
                    self::$redisConfig['port'] = (int)$_ENV['REDIS_PORT'];
                }
                if (isset($_ENV['REDIS_DB'])) {
                    self::$redisConfig['database'] = (int)$_ENV['REDIS_DB'];
                }
                if (isset($_ENV['REDIS_PASSWORD']) && $_ENV['REDIS_PASSWORD'] !== '') {
                    self::$redisConfig['password'] = $_ENV['REDIS_PASSWORD'];
                }

                if (!self::$useRedis) {
                    return;
                }

                self::$redis = new Client([
                    'scheme' => 'tcp',
                    'host' => self::$redisConfig['host'],
                    'port' => self::$redisConfig['port'],
                    'database' => self::$redisConfig['database'],
                    'password' => self::$redisConfig['password'],
                    'prefix' => self::$redisConfig['prefix']
                ]);

                // Test connection
                self::$redis->ping();

            } catch (Exception $e) {
                // Redis not available, use memory fallback
                self::$useRedis = false;
                self::$redis = null;
                error_log("Redis connection failed, falling back to memory cache: " . $e->getMessage());
            }
        }
    }

    public static function get($key) {
        // If cache is disabled, always return null
        if (!self::isEnabled()) {
            return null;
        }
        
        self::initRedis();

        $key = self::formatKey($key);

        if (self::$useRedis && self::$redis) {
            try {
                $data = self::$redis->get($key);
                if ($data !== null) {
                    return unserialize($data);
                }
            } catch (Exception $e) {
                // Fallback to memory cache
                self::$useRedis = false;
                return self::getFromMemory($key);
            }
        }

        return self::getFromMemory($key);
    }

    public static function set($key, $value, $ttl = null) {
        // If cache is disabled, do nothing
        if (!self::isEnabled()) {
            return true;
        }
        
        self::initRedis();
        $ttl = $ttl ?? self::$ttl;

        $key = self::formatKey($key);

        if (self::$useRedis && self::$redis) {
            try {
                $serializedValue = serialize($value);
                return self::$redis->setex($key, $ttl, $serializedValue);
            } catch (Exception $e) {
                // Fallback to memory cache
                self::$useRedis = false;
                self::setInMemory($key, $value, $ttl);
                return true;
            }
        }

        self::setInMemory($key, $value, $ttl);
        return true;
    }

    public static function delete($key) {
        // If cache is disabled, do nothing
        if (!self::isEnabled()) {
            return true;
        }
        
        self::initRedis();

        $key = self::formatKey($key);

        if (self::$useRedis && self::$redis) {
            try {
                return self::$redis->del([$key]) > 0;
            } catch (Exception $e) {
                // Fallback to memory cache
                self::$useRedis = false;
                self::deleteFromMemory($key);
                return true;
            }
        }

        self::deleteFromMemory($key);
        return true;
    }

    public static function clear() {
        // If cache is disabled, do nothing
        if (!self::isEnabled()) {
            return true;
        }
        
        self::initRedis();

        if (self::$useRedis && self::$redis) {
            try {
                return self::$redis->flushdb();
            } catch (Exception $e) {
                // Fallback to memory cache
                self::$useRedis = false;
                self::clearMemory();
                return true;
            }
        }

        self::clearMemory();
        return true;
    }

    public static function has($key) {
        // If cache is disabled, always return false
        if (!self::isEnabled()) {
            return false;
        }
        
        self::initRedis();

        $key = self::formatKey($key);

        if (self::$useRedis && self::$redis) {
            try {
                return self::$redis->exists($key) > 0;
            } catch (Exception $e) {
                // Fallback to memory cache
                self::$useRedis = false;
                return self::hasInMemory($key);
            }
        }

        return self::hasInMemory($key);
    }

    // Get cache statistics
    public static function getStats() {
        self::initRedis();

        if (self::$useRedis && self::$redis) {
            try {
                $info = self::$redis->info();
                return [
                    'driver' => 'redis',
                    'host' => self::$redisConfig['host'],
                    'port' => self::$redisConfig['port'],
                    'database' => self::$redisConfig['database'],
                    'connected_clients' => $info['Clients']['connected_clients'] ?? 'N/A',
                    'used_memory' => $info['Memory']['used_memory_human'] ?? 'N/A',
                    'keys_count' => self::$redis->dbsize()
                ];
            } catch (Exception $e) {
                return ['driver' => 'memory', 'error' => $e->getMessage()];
            }
        }

        return [
            'driver' => 'memory',
            'keys_count' => count(self::$fallbackCache),
            'memory_usage' => memory_get_usage(true)
        ];
    }

    private static function formatKey(string $key): string
    {
        $prefix = self::$redisConfig['prefix'] ?? '';
        if (!empty($prefix) && strpos($key, $prefix) !== 0) {
            return $prefix . $key;
        }
        return $key;
    }

    // Memory fallback methods
    private static function getFromMemory($key) {
        if (isset(self::$fallbackCache[$key])) {
            $data = self::$fallbackCache[$key];
            if ($data['expires'] > time()) {
                return $data['value'];
            } else {
                unset(self::$fallbackCache[$key]);
            }
        }
        return null;
    }

    private static function setInMemory($key, $value, $ttl) {
        self::$fallbackCache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }

    private static function deleteFromMemory($key) {
        unset(self::$fallbackCache[$key]);
    }

    private static function clearMemory() {
        self::$fallbackCache = [];
    }

    private static function hasInMemory($key) {
        return isset(self::$fallbackCache[$key]) && self::$fallbackCache[$key]['expires'] > time();
    }

    // Clean expired memory entries
    public static function cleanup() {
        $now = time();
        foreach (self::$fallbackCache as $key => $data) {
            if ($data['expires'] <= $now) {
                unset(self::$fallbackCache[$key]);
            }
        }
    }
}
