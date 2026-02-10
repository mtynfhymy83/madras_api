<?php

namespace App\Services\Storage;

use App\Contracts\StorageInterface;

class LocalStorage implements StorageInterface
{
    private string $basePath;
    private string $baseUrl;

    public function __construct()
    {
        $this->basePath = $_ENV['LOCAL_STORAGE_PATH'] ?? 'uploads';
        $this->baseUrl = $_ENV['LOCAL_STORAGE_URL'] ?? 'http://localhost:9501/uploads';
    }

    public function upload(string $source, string $destination, array $options = []): string
    {
        $fullPath = $this->basePath . '/' . $destination;
        
        // Create directory if not exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Check if source is file path or content
        if (file_exists($source)) {
            copy($source, $fullPath);
        } else {
            file_put_contents($fullPath, $source);
        }

        // Set permissions
        chmod($fullPath, 0644);

        return $destination;
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->basePath . '/' . $path;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->basePath . '/' . $path);
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/' . $path;
    }

    public function temporaryUrl(string $path, int $expiresIn = 3600): string
    {
        // For local storage, return regular URL
        // Could implement token-based access if needed
        return $this->url($path);
    }
}
