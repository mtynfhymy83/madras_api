<?php

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    protected array $errors;
    protected int $statusCode;

    // پیش‌فرض 422 برای Validation
    public function __construct(array $errors, $message = "Validation Failed", $statusCode = 422)
    {
        parent::__construct($message);
        $this->errors = $errors;
        $this->statusCode = $statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}