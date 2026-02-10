<?php

namespace App\Traits;

trait ResponseTrait {
    public function sendResponse($data=null, $message = '', $error = false, $status = 200) {
        $response = [
            'success' => !$error,
            'data' => $data,
            'message' => $message,
            'error' => $error,
            'status' => $status
        ];

        // Return the response array instead of echoing
        // The Swoole server will handle the output
        return $response;
    }

    public function view(){

    }
}