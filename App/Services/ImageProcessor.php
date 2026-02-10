<?php

namespace App\Services;

class ImageProcessor
{
    /**
     * Resize image and optionally convert to WebP
     */
    public function resize(string $sourcePath, string $destinationPath, int $width, int $height, array $options = []): bool
    {
        $quality = $options['quality'] ?? 85;
        $convertToWebP = $options['webp'] ?? false;
        $maintainAspectRatio = $options['maintain_aspect_ratio'] ?? true;

        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new \Exception('Invalid image file');
        }

        [$sourceWidth, $sourceHeight, $imageType] = $imageInfo;

        // Calculate dimensions
        if ($maintainAspectRatio) {
            $ratio = min($width / $sourceWidth, $height / $sourceHeight);
            $newWidth = (int)($sourceWidth * $ratio);
            $newHeight = (int)($sourceHeight * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // Create source image
        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                ? imagecreatefromwebp($sourcePath)
                : throw new \Exception('WebP is not supported on this server'),
            default => throw new \Exception('Unsupported image type'),
        };

        if (!$sourceImage) {
            throw new \Exception('Failed to create image resource');
        }

        // Create destination image
        $destImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
            imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize
        imagecopyresampled(
            $destImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $sourceWidth, $sourceHeight
        );

        // Create directory if not exists
        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Save image
        $success = false;
        if ($convertToWebP && function_exists('imagewebp')) {
            $success = imagewebp($destImage, $destinationPath, $quality);
        } else {
            $success = match ($imageType) {
                IMAGETYPE_JPEG => imagejpeg($destImage, $destinationPath, $quality),
                IMAGETYPE_PNG => imagepng($destImage, $destinationPath, (int)(9 - ($quality / 10))),
                IMAGETYPE_GIF => imagegif($destImage, $destinationPath),
                IMAGETYPE_WEBP => function_exists('imagewebp')
                    ? imagewebp($destImage, $destinationPath, $quality)
                    : false,
                default => false,
            };
        }

        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($destImage);

        return $success;
    }

    /**
     * Generate multiple sizes
     */
    public function generateSizes(string $sourcePath, string $baseDestPath, array $sizes): array
    {
        $results = [];
        $extension = pathinfo($baseDestPath, PATHINFO_EXTENSION);
        $baseName = pathinfo($baseDestPath, PATHINFO_FILENAME);
        $directory = pathinfo($baseDestPath, PATHINFO_DIRNAME);

        foreach ($sizes as $sizeName => $dimensions) {
            $destPath = "{$directory}/{$baseName}_{$sizeName}.{$extension}";
            
            $success = $this->resize(
                $sourcePath,
                $destPath,
                $dimensions['width'],
                $dimensions['height'],
                $dimensions['options'] ?? []
            );

            if ($success) {
                $results[$sizeName] = $destPath;

                // Generate WebP version if requested
                if (!empty($dimensions['webp'])) {
                    $webpPath = "{$directory}/{$baseName}_{$sizeName}.webp";
                    $this->resize(
                        $sourcePath,
                        $webpPath,
                        $dimensions['width'],
                        $dimensions['height'],
                        array_merge($dimensions['options'] ?? [], ['webp' => true])
                    );
                    $results["{$sizeName}_webp"] = $webpPath;
                }
            }
        }

        return $results;
    }

    /**
     * Optimize image
     */
    public function optimize(string $imagePath): bool
    {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }

        [$width, $height, $imageType] = $imageInfo;

        // Re-save with optimization
        return $this->resize($imagePath, $imagePath, $width, $height, ['quality' => 85]);
    }
}
