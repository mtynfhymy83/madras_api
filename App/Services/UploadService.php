<?php

namespace App\Services;

use App\Services\Storage\StorageManager;
use App\Services\Storage\PathGenerator;

class UploadService
{
    private StorageManager $storage;
    private ImageProcessor $imageProcessor;
    private FileValidator $validator;

    public function __construct()
    {
        $this->storage = new StorageManager();
        $this->imageProcessor = new ImageProcessor();
        $this->validator = new FileValidator();
    }

    /**
     * Upload file with automatic processing
     */
    public function upload(string $source, array $config): array
    {
        $type = $config['type'] ?? 'file';
        $disk = $config['disk'] ?? null;
        $userFolder = $config['user_folder'] ?? 'admin';
        $originalFilename = $config['filename'] ?? basename($source);
        $generateSizes = $config['sizes'] ?? [];
        $optimize = $config['optimize'] ?? true;

        // Validate file
        $validation = $this->validator->validate($source, $type, $config['validation'] ?? []);
        if (!$validation['valid']) {
            throw new \Exception('اعتبارسنجی فایل ناموفق: ' . implode(', ', $validation['errors']));
        }

        // Generate organized path
        $pathInfo = PathGenerator::generate($userFolder, $originalFilename, [
            'date' => $config['date'] ?? null,
            'make_unique' => $config['make_unique'] ?? false,
        ]);
        
        $destination = $pathInfo['full_path'];

        $results = [
            'original' => null,
            'sizes' => [],
            'webp' => [],
        ];

        // Process image if needed
        if ($type === 'image' && !empty($generateSizes)) {
            $tempDir = sys_get_temp_dir() . '/uploads_' . uniqid();
            mkdir($tempDir, 0755, true);

            try {
                // Optimize original if requested
                if ($optimize) {
                    $optimizedPath = $tempDir . '/optimized_' . $originalFilename;
                    $imageInfo = getimagesize($source);
                    if ($imageInfo) {
                        $this->imageProcessor->resize(
                            $source,
                            $optimizedPath,
                            $imageInfo[0], // width
                            $imageInfo[1], // height
                            ['quality' => 85]
                        );
                        $uploadSource = $optimizedPath;
                    } else {
                        $uploadSource = $source;
                    }
                } else {
                    $uploadSource = $source;
                }

                // Upload original
                $originalPath = $this->storage->disk($disk)->upload($uploadSource, $destination);
                
                // Validate uploaded path before generating URL
                if (empty($originalPath)) {
                    throw new \Exception('Upload failed: empty path returned');
                }
                
                $results['original'] = [
                    'path' => $originalPath,
                    'url' => $this->storage->disk($disk)->url($originalPath),
                ];

                // Generate and upload different sizes
                foreach ($generateSizes as $sizeName => $sizeConfig) {
                    $sizeWidth = $sizeConfig['width'];
                    $sizePath = PathGenerator::generateSizePath(
                        $pathInfo['directory'] . '/' . $pathInfo['name_without_ext'],
                        $sizeWidth,
                        $pathInfo['extension']
                    );
                    $tempSizePath = $tempDir . '/' . basename($sizePath);

                    // Resize
                    $this->imageProcessor->resize(
                        $uploadSource,
                        $tempSizePath,
                        $sizeConfig['width'],
                        $sizeConfig['height'],
                        $sizeConfig['options'] ?? []
                    );

                    // Upload resized image
                    $uploadedPath = $this->storage->disk($disk)->upload($tempSizePath, $sizePath);
                    $results['sizes'][$sizeName] = [
                        'path' => $uploadedPath,
                        'url' => $this->storage->disk($disk)->url($uploadedPath),
                        'width' => $sizeConfig['width'],
                        'height' => $sizeConfig['height'],
                    ];

                    // Generate WebP version if requested
                    if (!empty($sizeConfig['webp']) && function_exists('imagewebp')) {
                        $webpPath = PathGenerator::generateSizePath(
                            $pathInfo['directory'] . '/' . $pathInfo['name_without_ext'],
                            $sizeWidth,
                            'webp'
                        );
                        $tempWebpPath = $tempDir . '/' . basename($webpPath);

                        $this->imageProcessor->resize(
                            $uploadSource,
                            $tempWebpPath,
                            $sizeConfig['width'],
                            $sizeConfig['height'],
                            array_merge($sizeConfig['options'] ?? [], ['webp' => true])
                        );

                        $uploadedWebpPath = $this->storage->disk($disk)->upload($tempWebpPath, $webpPath);
                        $results['webp'][$sizeName] = [
                            'path' => $uploadedWebpPath,
                            'url' => $this->storage->disk($disk)->url($uploadedWebpPath),
                        ];
                    }
                }
            } finally {
                // Cleanup temp files
                $this->cleanupDirectory($tempDir);
            }
        } else {
            // Direct upload without processing
            $uploadedPath = $this->storage->disk($disk)->upload($source, $destination);
            
            // Validate uploaded path before generating URL
            if (empty($uploadedPath)) {
                throw new \Exception('Upload failed: empty path returned');
            }
            
            $results['original'] = [
                'path' => $uploadedPath,
                'url' => $this->storage->disk($disk)->url($uploadedPath),
            ];
        }

        return $results;
    }

    /**
     * Upload from base64 string
     */
    public function uploadFromBase64(string $base64String, array $config): array
    {
        // Normalize base64 input - remove all whitespace including newlines
        $base64String = trim($base64String);
        $base64String = preg_replace('/\s+/', '', $base64String); // Remove all whitespace
        
        if (empty($base64String)) {
            throw new \Exception('رشته base64 خالی است');
        }

        // Support both data URI and raw base64
        $encoded = $base64String;
        if (str_contains($base64String, ',')) {
            $parts = explode(',', $base64String, 2);
            if (count($parts) === 2 && !empty($parts[1])) {
                $encoded = $parts[1];
            } else {
                throw new \Exception('فرمت base64 نامعتبر است (باید شامل کاما باشد)');
            }
        }

        // Remove any non-base64 characters (keep only A-Za-z0-9+/= and handle URL-safe)
        // First, convert URL-safe base64 to standard base64
        $encoded = str_replace(['-', '_'], ['+', '/'], $encoded);
        
        // Remove any invalid characters (keep only valid base64 chars)
        $encoded = preg_replace('/[^A-Za-z0-9+\/=]/', '', $encoded);
        
        if (empty($encoded)) {
            throw new \Exception('رشته base64 پس از پاکسازی خالی شد - کاراکترهای نامعتبر وجود دارد');
        }

        // Fix padding - base64 strings must be multiple of 4
        $padLen = strlen($encoded) % 4;
        if ($padLen > 0) {
            $encoded .= str_repeat('=', 4 - $padLen);
        }

        // Try to decode
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || empty($decoded)) {
            // Provide more detailed error
            $errorDetails = [
                'original_length' => strlen($base64String),
                'encoded_length' => strlen($encoded),
                'has_data_uri' => str_contains($base64String, 'data:'),
                'first_chars' => substr($encoded, 0, 20),
            ];
            throw new \Exception(
                'خطا در decode کردن base64. ' .
                'طول رشته: ' . strlen($encoded) . ' کاراکتر. ' .
                'لطفاً مطمئن شوید که base64 string کامل و معتبر است.'
            );
        }

        // Create temp file
        $tempFile = sys_get_temp_dir() . '/upload_' . uniqid() . '_' . time();
        $written = file_put_contents($tempFile, $decoded);

        if ($written === false) {
            throw new \Exception('خطا در نوشتن فایل موقت');
        }

        try {
            return $this->upload($tempFile, $config);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Delete uploaded file and all its variants
     */
    public function delete(array $uploadedFiles, ?string $disk = null): bool
    {
        $storage = $this->storage->disk($disk);
        $success = true;

        // Delete original
        if (!empty($uploadedFiles['original']['path'])) {
            $success = $storage->delete($uploadedFiles['original']['path']) && $success;
        }

        // Delete sizes
        foreach ($uploadedFiles['sizes'] ?? [] as $size) {
            if (!empty($size['path'])) {
                $success = $storage->delete($size['path']) && $success;
            }
        }

        // Delete webp versions
        foreach ($uploadedFiles['webp'] ?? [] as $webp) {
            if (!empty($webp['path'])) {
                $success = $storage->delete($webp['path']) && $success;
            }
        }

        return $success;
    }


    /**
     * Cleanup directory
     */
    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($dir);
    }
}
