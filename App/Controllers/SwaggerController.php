<?php

namespace App\Controllers;

use App\Controllers\Controller;

class SwaggerController extends Controller
{
    public function index($request = null)
    {
        $filePath = __DIR__ . '/../../swagger.html';
        if (file_exists($filePath)) {
            // Return raw HTML content as string
            return file_get_contents($filePath);
        }
        
        return $this->sendResponse(null, "Swagger file not found", true, 404);
    }
    
    public function yaml($request = null)
    {
        $filePath = __DIR__ . '/../../docs/swagger.yaml';
        if (file_exists($filePath)) {
            // Return raw YAML content as string
            return file_get_contents($filePath);
        }
        
        return $this->sendResponse(null, "Swagger YAML file not found", true, 404);
    }
}

