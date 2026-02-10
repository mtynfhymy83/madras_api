<?php

namespace App\Cache;

interface CacheStoreInterface
{
    public function get(string $key);
    public function set(string $key, $value, int $ttl): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
    public function clear(): void;
}
