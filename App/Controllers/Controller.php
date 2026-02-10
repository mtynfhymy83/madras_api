<?php

namespace App\Controllers;

use App\Database\QueryBuilder;
use App\Middlewares\CheckAccessMiddleware;
use App\Traits\ResponseTrait;
use App\Validations\Validator;

class Controller
{
    use ResponseTrait;

    protected Validator $validator;

    protected $queryBuilder;
    protected $Access;

    public function __construct()
    {
        $this->queryBuilder = new QueryBuilder();
        $this->Access       = new CheckAccessMiddleware();
        $this->validator    = new Validator();
    }
    
    /**
     * Validate data using Validator
     */
    protected function validate(array $rules, $data): void
    {
        // Convert object to array if needed
        $dataArray = [];
        if (is_object($data)) {
            foreach ($rules as $field => $ruleString) {
                $dataArray[$field] = $data->$field ?? null;
            }
        } elseif (is_array($data)) {
            $dataArray = $data;
        } else {
            $dataArray = [];
        }
        
        if (!$this->validator->validate($dataArray, $rules)) {
            $errors = $this->validator->getErrors();
            throw new \App\Exceptions\ValidationException($errors);
        }
    }
    
    /**
     * Check unique values in database
     */
    protected function checkUnique(string $table, array $conditions): void
    {
        foreach ($conditions as $condition) {
            [$field, $value] = $condition;
            
            if (!$this->validator->validate([$field => $value], [
                $field => "unique:$table,$field"
            ])) {
                $errors = $this->validator->getErrors();
                throw new \App\Exceptions\ValidationException($errors);
            }
        }
    }
    
    public function __destruct()
    {
        // Ensure we always return an array response
        if (ob_get_length()) {
            ob_clean();
        }
    }
}