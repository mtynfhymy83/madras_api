<?php

namespace App\Cache;

use Predis\Client;
use Exception;
use Swoole\Coroutine;

/**
 * Redis Cache Implementation using Predis (pure PHP)
 * Fallback when php-redis extension is not available
 * 
 * Note: Slower than RedisCache but works without extensions
 */
final class PredisCache implements CacheStoreInterface
{
    private array $config;
    private string $clientKey;
    private ?Client $client = null;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        int $database = 0,
        ?string $password = null,
        string $prefix = ''
    ) {
        $this->config = [
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'password' => $password,
            'prefix' => $prefix,
        ];

        $this->clientKey = 'predis_client_' . spl_object_id($this);

        // If not in coroutine, initialize a single client for CLI/non-Swoole usage
        if (Coroutine::getCid() <= 0) {
            $this->client = $this->createClient();
        }
    }

    private function createClient(): Client
    {
        $client = new Client($this->config);
        try {
            $client->ping();
        } catch (Exception $e) {
            throw new Exception("Failed to connect to Redis: " . $e->getMessage());
        }
        return $client;
    }

    private function getClient(): Client
    {
        if (Coroutine::getCid() > 0) {
            $ctx = Coroutine::getContext();
            if (!isset($ctx[$this->clientKey]) || !($ctx[$this->clientKey] instanceof Client)) {
                $ctx[$this->clientKey] = $this->createClient();
            }
            return $ctx[$this->clientKey];
        }

        if (!$this->client) {
            $this->client = $this->createClient();
        }
        return $this->client;
    }

    public function get(string $key)
    {
        try {
            $value = $this->getClient()->get($key);
            return $value !== null ? unserialize($value) : null;
        } catch (Exception $e) {
            error_log("Predis get error: " . $e->getMessage());
            return null;
        }
    }

    public function set(string $key, $value, int $ttl): void
    {
        try {
            $serialized = serialize($value);
            if ($ttl > 0) {
                $this->getClient()->setex($key, $ttl, $serialized);
            } else {
                $this->getClient()->set($key, $serialized);
            }
        } catch (Exception $e) {
            error_log("Predis set error: " . $e->getMessage());
        }
    }

    public function delete(string $key): void
    {
        try {
            $this->getClient()->del([$key]);
        } catch (Exception $e) {
            error_log("Predis delete error: " . $e->getMessage());
        }
    }

    public function has(string $key): bool
    {
        try {
            return $this->getClient()->exists($key) > 0;
        } catch (Exception $e) {
            error_log("Predis exists error: " . $e->getMessage());
            return false;
        }
    }

    public function clear(): void
    {
        try {
            $this->getClient()->flushdb();
        } catch (Exception $e) {
            error_log("Predis flushDB error: " . $e->getMessage());
        }
    }
}
