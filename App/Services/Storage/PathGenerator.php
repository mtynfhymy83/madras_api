<?php

namespace App\Services\Storage;

class PathGenerator
{
    /**
     * Generate organized path based on user type and date
     * 
     * @param string $baseFolder Base folder (e.g., 'admin', 'user123', 'teacher456')
     * @param string $filename Original filename
     * @param array $options Additional options
     * @return array ['directory' => '...', 'filename' => '...', 'full_path' => '...']
     */
    public static function generate(string $baseFolder, string $filename, array $options = []): array
    {
        // Get date parts
        $year = date('Y');
        $month = date('m');
        
        // Override date if provided
        if (!empty($options['date'])) {
            $date = strtotime($options['date']);
            $year = date('Y', $date);
            $month = date('m', $date);
        }
        
        // Clean filename
        $cleanFilename = self::cleanFilename($filename);
        $extension = pathinfo($cleanFilename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($cleanFilename, PATHINFO_FILENAME);
        
        // Create folder name from filename
        $folderName = $nameWithoutExt;
        
        // Build directory structure: uploads/admin/2025/01/my-image/
        $directory = sprintf(
            'uploads/%s/%s/%s/%s',
            $baseFolder,
            $year,
            $month,
            $folderName
        );
        
        // Generate unique filename if needed
        $finalFilename = $nameWithoutExt;
        if (!empty($options['make_unique'])) {
            $finalFilename .= '-' . uniqid();
        }
        
        $fullFilename = $finalFilename . '.' . $extension;
        
        return [
            'directory' => $directory,
            'filename' => $fullFilename,
            'full_path' => $directory . '/' . $fullFilename,
            'name_without_ext' => $finalFilename,
            'extension' => $extension,
        ];
    }
    
    /**
     * Generate path for specific size
     * 
     * @param string $basePath Base path without extension
     * @param string $size Size name (e.g., '150', '300', '600')
     * @param string $extension File extension
     * @return string
     */
    public static function generateSizePath(string $basePath, string $size, string $extension): string
    {
        $pathInfo = pathinfo($basePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        
        return sprintf(
            '%s/%s-%s.%s',
            $directory,
            $filename,
            $size,
            $extension
        );
    }
    
    /**
     * Clean filename (remove special characters)
     */
    private static function cleanFilename(string $filename): string
    {
        // Get extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        
        // Remove special characters
        $nameWithoutExt = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $nameWithoutExt);
        
        // Remove multiple dashes
        $nameWithoutExt = preg_replace('/-+/', '-', $nameWithoutExt);
        
        // Trim dashes
        $nameWithoutExt = trim($nameWithoutExt, '-');
        
        // Lowercase
        $nameWithoutExt = strtolower($nameWithoutExt);
        
        // Limit length
        if (strlen($nameWithoutExt) > 50) {
            $nameWithoutExt = substr($nameWithoutExt, 0, 50);
        }
        
        // If empty, use timestamp
        if (empty($nameWithoutExt)) {
            $nameWithoutExt = 'file-' . time();
        }
        
        return $nameWithoutExt . '.' . $extension;
    }
    
    /**
     * Get user folder based on role and ID
     */
    public static function getUserFolder(array $user): string
    {
        $role = $user['role'] ?? 'user';
        $userId = $user['id'] ?? 'unknown';
        
        // Admin folder
        if ($role === 'admin') {
            return 'admin';
        }
        
        // Teacher folder
        if ($role === 'teacher') {
            return 'teacher' . $userId;
        }
        
        // Regular user folder
        return 'user' . $userId;
    }
}
