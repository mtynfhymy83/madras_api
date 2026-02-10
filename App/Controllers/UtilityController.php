<?php

namespace App\Controllers;

class UtilityController
{
    /**
     * Show Base64 Converter tool
     */
    public function base64Converter()
    {
        $filePath = __DIR__ . '/../../base64_converter.html';
        
        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }
        
        return json_encode([
            'success' => false,
            'message' => 'Base64 converter not found',
            'error' => true,
            'status' => 404
        ]);
    }
}
