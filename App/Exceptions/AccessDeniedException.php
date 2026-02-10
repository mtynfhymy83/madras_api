<?php

namespace App\Exceptions;

use Exception;

class AccessDeniedException extends Exception
{
    protected int $statusCode;

    public function __construct($message = "Access Denied", $statusCode = 403)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}






