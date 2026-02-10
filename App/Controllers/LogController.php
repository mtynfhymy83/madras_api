<?php

namespace App\Controllers;

class LogController
{
    public function index(): array
    {
        $logFile = '/var/www/html/storage/logs/swoole.log';
        
        if (!file_exists($logFile)) {
            return [
                'success' => false,
                'data' => "Log file not found at: $logFile",
                'message' => 'Log file not found',
                'content_type' => 'text/plain'
            ];
        }
        
        // Read last 200 lines
        $lines = [];
        $file = new \SplFileObject($logFile);
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        
        $startLine = max(0, $lastLine - 200);
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $lines[] = $file->current();
            $file->next();
        }
        
        return [
            'success' => true,
            'data' => implode('', $lines),
            'message' => 'Logs',
            'content_type' => 'text/plain'
        ];
    }
}
