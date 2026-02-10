<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

class QueryBuilder
{
    protected string $table = '';
    protected array $select = ['*'];
    protected array $wheres = [];
    protected array $joins = [];
    protected array $bindings = [];
    protected ?string $groupBy = null;
    protected ?string $orderBy = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected bool $disableSoftDelete = false;
    protected int $fetchMode = PDO::FETCH_ASSOC;

    // ------------------ BUILDER METHODS ------------------

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select($columns = ['*']): self
    {
        $this->select = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function where(string $column, string $operator = '=', $value = null): self
    {
        if ($value === null) {
            $col = $this->wrap($column);
            if (in_array(strtoupper($operator), ['=', 'IS'], true)) {
                $this->wheres[] = "$col IS NULL";
            } else {
                $this->wheres[] = "$col IS NOT NULL";
            }
            return $this;
        }

        $this->wheres[] = $this->wrap($column) . " $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = $this->wrap($column) . " " . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = $type . ' JOIN ' . $this->wrap($table) . ' ON ' . $this->wrap($first) . ' ' . $operator . ' ' . $this->wrap($second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function withoutSoftDelete(): self
    {
        $this->disableSoftDelete = true;
        return $this;
    }

    // ------------------ EXECUTION METHODS ------------------

    public function get(): array
    {
        $this->ensureTable();

        $sql = $this->compileSelect();
        $params = $this->bindings;
        $fetchMode = $this->fetchMode;

        $this->reset();

        return DB::run(function (PDO $pdo) use ($sql, $params, $fetchMode) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll($fetchMode);
        });
    }

    public function first()
    {
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? null;
    }

    public function insert(array $values, bool $returnId = true)
    {
        $this->ensureTable();

        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($values as $col => $val) {
            $columns[] = $this->wrap($col);
            $placeholders[] = '?';
            $params[] = $val;
        }

        $colsSql = implode(', ', $columns);
        $valsSql = implode(', ', $placeholders);
        $table = $this->wrap($this->table);

        $sql = "INSERT INTO $table ($colsSql) VALUES ($valsSql)";
        
        // Only add RETURNING id if requested and table likely has id column
        if ($returnId) {
            $sql .= " RETURNING id";
        }

        $this->reset();

        return DB::run(function (PDO $pdo) use ($sql, $params, $returnId) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            if ($returnId) {
                try {
                    return $stmt->fetchColumn();
                } catch (\Exception $e) {
                    return true;
                }
            }
            
            return true;
        });
    }

    public function update(array $values): bool
    {
        $this->ensureTable();

        $sets = [];
        $updateParams = [];

        foreach ($values as $col => $val) {
            $sets[] = $this->wrap($col) . " = ?";
            $updateParams[] = $val;
        }

        // Do not add soft delete condition here; compileWheres handles it
        $sql = "UPDATE " . $this->wrap($this->table) . " SET " . implode(', ', $sets);
        $sql .= $this->compileWheres();

        $finalParams = array_merge($updateParams, $this->bindings);

        $this->reset();

        return DB::run(function (PDO $pdo) use ($sql, $finalParams) {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($finalParams);
        });
    }

    public function delete(): bool
    {
        // Soft delete: update deleted_at instead of removing the row
        return $this->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function forceDelete(): bool
    {
        $this->ensureTable();
        
        // Include soft-deleted rows for actual removal
        $this->disableSoftDelete = true;

        $sql = "DELETE FROM " . $this->wrap($this->table);
        $sql .= $this->compileWheres();
        $params = $this->bindings;

        $this->reset();

        return DB::run(function (PDO $pdo) use ($sql, $params) {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        });
    }

    // ------------------ INTERNAL HELPERS ------------------

    private function wrap(string $value): string
    {
        if ($value === '*') return $value;

        // SQL functions or expressions (e.g. COUNT, AS) – leave as-is
        if (str_contains($value, '(') || str_contains($value, ' as ') || str_contains($value, ' AS ')) {
            return $value;
        }

        // Qualified names: users.id, users.*
        if (str_contains($value, '.')) {
            $parts = explode('.', $value, 2);
            $table = $parts[0];
            $col = $parts[1];

            if ($col === '*') {
                return '"' . $table . '".*';
            }

            return '"' . $table . '"."' . $col . '"';
        }

        // Simple column name
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function compileSelect(): string
    {
        $cols = implode(', ', array_map([$this, 'wrap'], $this->select));
        $table = $this->wrap($this->table);

        $sql = "SELECT $cols FROM $table";
        
        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        
        $sql .= $this->compileWheres();

        if ($this->groupBy) $sql .= " GROUP BY {$this->groupBy}";
        if ($this->orderBy) $sql .= " ORDER BY {$this->orderBy}";
        if ($this->limit)   $sql .= " LIMIT {$this->limit}";
        if ($this->offset)  $sql .= " OFFSET {$this->offset}";


        return $sql;
    }

    private function compileWheres(): string
    {
        $conditions = $this->wheres;

        if (!$this->disableSoftDelete) {
            // Extract alias from table name (e.g. "user_roles as ur" -> "ur")
            $tableAlias = $this->table;
            if (stripos($this->table, ' as ') !== false) {
                $parts = preg_split('/\s+as\s+/i', $this->table);
                $tableAlias = trim($parts[1] ?? $parts[0]);
            }
            $table = $this->wrap($tableAlias);
            $conditions[] = "$table.\"deleted_at\" IS NULL";
        }

        if (empty($conditions)) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', $conditions);
    }

    private function ensureTable(): void
    {
        if ($this->table === '') {
            throw new \LogicException('Table name not specified.');
        }
    }

    private function reset(): void
    {
        $this->select = ['*'];
        $this->wheres = [];
        $this->joins = [];
        $this->bindings = [];
        $this->groupBy = null;
        $this->orderBy = null;
        $this->limit = null;
        $this->offset = null;
        $this->disableSoftDelete = false;
        $this->fetchMode = PDO::FETCH_ASSOC;
    }
    public function count(): int
    {
        $this->ensureTable();

        $table = $this->wrap($this->table);
        $sql = "SELECT COUNT(*) FROM $table";
        
        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        
        $sql .= $this->compileWheres();
        
        $params = $this->bindings;
        
        $this->reset();

        return (int)DB::run(function (PDO $pdo) use ($sql, $params) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        });
    }
}