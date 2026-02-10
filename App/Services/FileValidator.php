<?php

namespace App\Services;

class FileValidator
{
    private array $allowedMimeTypes = [
        'image' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'audio' => [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
        ],
        'video' => [
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
        ],
    ];

    private array $maxSizes = [
        'image' => 5 * 1024 * 1024,      // 5MB
        'document' => 50 * 1024 * 1024,  // 50MB
        'audio' => 20 * 1024 * 1024,     // 20MB
        'video' => 100 * 1024 * 1024,    // 100MB
    ];

    /**
     * Validate uploaded file
     */
    public function validate(string $filePath, string $type, array $options = []): array
    {
        $errors = [];

        // Check file exists
        if (!file_exists($filePath)) {
            $errors[] = 'فایل وجود ندارد';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size
        $fileSize = filesize($filePath);
        $maxSize = $options['max_size'] ?? $this->maxSizes[$type] ?? (10 * 1024 * 1024);
        
        if ($fileSize > $maxSize) {
            $errors[] = sprintf('حجم فایل نباید بیشتر از %s باشد', $this->formatBytes($maxSize));
        }

        // Check mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $allowedTypes = $options['allowed_types'] ?? $this->allowedMimeTypes[$type] ?? [];
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            $errors[] = 'نوع فایل مجاز نیست';
        }

        // Additional image validation
        if ($type === 'image') {
            $imageInfo = getimagesize($filePath);
            if (!$imageInfo) {
                $errors[] = 'فایل تصویر معتبر نیست';
            } else {
                [$width, $height] = $imageInfo;
                
                if (isset($options['min_width']) && $width < $options['min_width']) {
                    $errors[] = sprintf('عرض تصویر باید حداقل %dpx باشد', $options['min_width']);
                }
                
                if (isset($options['min_height']) && $height < $options['min_height']) {
                    $errors[] = sprintf('ارتفاع تصویر باید حداقل %dpx باشد', $options['min_height']);
                }
                
                if (isset($options['max_width']) && $width > $options['max_width']) {
                    $errors[] = sprintf('عرض تصویر نباید بیشتر از %dpx باشد', $options['max_width']);
                }
                
                if (isset($options['max_height']) && $height > $options['max_height']) {
                    $errors[] = sprintf('ارتفاع تصویر نباید بیشتر از %dpx باشد', $options['max_height']);
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mimeType ?? null,
            'size' => $fileSize,
        ];
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
