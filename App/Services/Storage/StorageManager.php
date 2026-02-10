<?php

namespace App\Services\Storage;

use App\Contracts\StorageInterface;

class StorageManager
{
    private array $drivers = [];
    private string $defaultDriver;

    public function __construct()
    {
        $this->defaultDriver = $_ENV['STORAGE_DRIVER'] ?? 'local';
    }

    /**
     * Get storage driver
     */
    public function disk(?string $driver = null): StorageInterface
    {
        $driver = $driver ?? $this->defaultDriver;

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create storage driver instance
     */
    private function createDriver(string $driver): StorageInterface
    {
        return match ($driver) {
            'local' => new LocalStorage(),
            's3' => new S3Storage(),
            default => throw new \InvalidArgumentException("Unsupported storage driver: {$driver}"),
        };
    }

    /**
     * Get default driver name
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }
}
