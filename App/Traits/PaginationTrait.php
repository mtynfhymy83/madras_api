<?php

namespace App\Traits;

trait PaginationTrait {
    /**
     * Apply pagination to query and return paginated results with metadata
     * 
     * @param mixed $request Request data containing page and per_page
     * @param int $defaultPerPage Default items per page
     * @return array ['offset' => int, 'limit' => int, 'page' => int, 'per_page' => int]
     */
    protected function getPaginationParams($request, int $defaultPerPage = 20): array
    {
        $page = 1;
        $perPage = $defaultPerPage;

        if (is_object($request)) {
            $page = isset($request->page) && is_numeric($request->page) && $request->page > 0 
                ? (int)$request->page 
                : 1;
            $perPage = isset($request->per_page) && is_numeric($request->per_page) && $request->per_page > 0 
                ? min((int)$request->per_page, 100) // Max 100 items per page
                : $defaultPerPage;
        } elseif (is_array($request)) {
            $page = isset($request['page']) && is_numeric($request['page']) && $request['page'] > 0 
                ? (int)$request['page'] 
                : 1;
            $perPage = isset($request['per_page']) && is_numeric($request['per_page']) && $request['per_page'] > 0 
                ? min((int)$request['per_page'], 100)
                : $defaultPerPage;
        }

        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
            'limit' => $perPage
        ];
    }

    /**
     * Build pagination metadata for response
     * 
     * @param int $total Total number of items
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return array
     */
    protected function buildPaginationMeta(int $total, int $page, int $perPage): array
    {
        $lastPage = $perPage > 0 ? ceil($total / $perPage) : 1;

        return [
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total)
            ]
        ];
    }

    /**
     * Get total count for a table with optional where conditions
     * 
     * @param string $table Table name
     * @param array $whereConditions Array of where conditions
     * @return int
     */
    protected function getTotalCount(string $table, array $whereConditions = []): int
    {
        $query = $this->queryBuilder->table($table)->select('COUNT(*) as total');
        
        foreach ($whereConditions as $condition) {
            if (isset($condition['column'], $condition['operator'], $condition['value'])) {
                $query->where($condition['column'], $condition['operator'], $condition['value']);
            }
        }
        
        $result = $query->get()->execute();
        return isset($result->total) ? (int)$result->total : 0;
    }
}

