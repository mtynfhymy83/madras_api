<?php

namespace App\Contracts;

interface StorageInterface
{
    /**
     * Upload file to storage
     *
     * @param string $source Source file path or content
     * @param string $destination Destination path
     * @param array $options Additional options
     * @return string Public URL or path
     */
    public function upload(string $source, string $destination, array $options = []): string;

    /**
     * Delete file from storage
     *
     * @param string $path File path
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * Check if file exists
     *
     * @param string $path File path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Get file URL
     *
     * @param string $path File path
     * @return string
     */
    public function url(string $path): string;

    /**
     * Get temporary URL (signed URL)
     *
     * @param string $path File path
     * @param int $expiresIn Expiration time in seconds
     * @return string
     */
    public function temporaryUrl(string $path, int $expiresIn = 3600): string;
}
