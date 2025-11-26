<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MigrateMysqlToPostgres extends Command
{
    /**
     * Copy data from default MySQL connection to pgsql_migrate.
     */
    protected $signature = 'data:migrate-mysql-to-pg
        {--tables= : Comma-separated list of tables to copy. Defaults to all user tables}
        {--truncate : Truncate target tables before copying}
        {--chunk=2000 : Chunk size for copying rows}
        {--skip=migrations,failed_jobs,personal_access_tokens : Comma-separated tables to skip}
        {--allow-truncation : Allow lossy truncation of overlength strings to fit PostgreSQL column limits}
        {--users-skip-duplicate-emails : For users table, skip rows that conflict on unique email}';

    protected $description = 'Copy data from MySQL to PostgreSQL using Laravel connections (with chunking, optional truncate, trigger disable, and sequence reset).';

    public function handle(): int
    {
        $mysql = DB::connection('mysql');
        $pg = DB::connection('pgsql_migrate');

        $chunkSize = max(1, (int) $this->option('chunk'));
        $explicitTables = $this->option('tables');
        $skip = $this->parseCsvOption((string) $this->option('skip'));
        $this->allowTruncation = (bool) $this->option('allow-truncation');
        $this->usersSkipDuplicateEmails = (bool) $this->option('users-skip-duplicate-emails');

        $tables = $explicitTables
            ? $this->parseCsvOption($explicitTables)
            : $this->discoverMySqlTables($mysql)->diff($skip)->values()->all();

        if (empty($tables)) {
            $this->warn('No tables selected for migration.');
            return self::SUCCESS;
        }

        // Optionally truncate target tables in reverse order to reduce FK issues
        if ($this->option('truncate')) {
            $this->info('Truncating target tables (PostgreSQL)...');
            foreach (array_reverse($tables) as $table) {
                $this->truncatePgTable($pg, $table);
            }
        }

        // Disable triggers to bypass FK checks during bulk load
        $this->setPgTriggers($pg, disable: true);

        try {
            foreach ($tables as $table) {
                $this->copyTable($mysql, $pg, $table, $chunkSize);
            }
        } finally {
            // Always re-enable triggers
            $this->setPgTriggers($pg, disable: false);
        }

        // Reset sequences to MAX(id)
        $this->resetPgSequences($pg);

        $this->info('Data migration completed successfully.');
        return self::SUCCESS;
    }

    /**
     * Discover MySQL base tables in current schema.
     */
    protected function discoverMySqlTables(ConnectionInterface $mysql): Collection
    {
        $database = $mysql->getDatabaseName();

        $rows = $mysql->select(<<<SQL
            SELECT TABLE_NAME
            FROM information_schema.tables
            WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        SQL, [$database]);

        return collect($rows)->pluck('TABLE_NAME')->filter(function ($name) {
            return is_string($name);
        });
    }

    /**
     * Copy one table from MySQL to PostgreSQL in chunks.
     */
    protected function copyTable(ConnectionInterface $mysql, ConnectionInterface $pg, string $table, int $chunkSize): void
    {
        $this->line("Migrating table: {$table}");

        $columns = $this->getSharedColumns($mysql, $pg, $table);
        if (empty($columns)) {
            $this->warn(" - Skipping {$table}: no common columns detected.");
            return;
        }

        $hasId = $this->tableHasColumn($mysql, $table, 'id');
        $limits = $this->getPgColumnLimits($pg, $table);

        $copied = 0;
        if ($hasId) {
            // Chunk by id for stable pagination
            $lastId = 0;
            while (true) {
                $rows = $mysql->table($table)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($chunkSize)
                    ->get($columns);

                if ($rows->isEmpty()) {
                    break;
                }

                $payload = $rows->map(function ($r) use ($limits, $table) {
                    $row = $this->applyColumnLimits((array) $r, $limits, $table);
                    return $this->postProcessRow($row, $table);
                })->all();
                $this->insertRows($pg, $table, $columns, $payload);
                $copied += count($payload);
                $lastId = (int) Arr::last($payload)['id'];
            }
        } else {
            // Fallback: offset pagination
            $offset = 0;
            while (true) {
                $rows = $mysql->table($table)
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get($columns);

                if ($rows->isEmpty()) {
                    break;
                }

                $payload = $rows->map(function ($r) use ($limits, $table) {
                    $row = $this->applyColumnLimits((array) $r, $limits, $table);
                    return $this->postProcessRow($row, $table);
                })->all();
                $this->insertRows($pg, $table, $columns, $payload);
                $copied += count($payload);
                $offset += $chunkSize;
            }
        }

        $this->info(" - Copied {$copied} rows");
    }

    /**
     * Get columns present in both source MySQL and target Postgres tables.
     */
    protected function getSharedColumns(ConnectionInterface $mysql, ConnectionInterface $pg, string $table): array
    {
        $mysqlCols = $this->listColumnsMySql($mysql, $table);
        $pgCols = $this->listColumnsPg($pg, $table);

        return array_values(array_intersect($mysqlCols, $pgCols));
    }

    protected function postProcessRows(array $row, string $table): array
{
    // پاکسازی ستون‌های تاریخ در تمام جدول‌ها
    foreach ($row as $key => $value) {
        if (in_array(strtolower($key), ['date', 'created_at', 'updated_at', 'deleted_at', 'birthdate', 'dob']) ||
            str_ends_with(strtolower($key), '_date') ||
            str_ends_with(strtolower($key), '_at')) {

            // اگر مقدار رشته خالی یا مقدار اشتباه باشد → null
            if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                $row[$key] = null;
            }
        }
    }

    // پردازش ویژه جدول users
    if ($table === 'users') {
        if (array_key_exists('email', $row)) {
            $email = is_null($row['email']) ? null : trim((string) $row['email']);
            $row['email'] = ($email === '') ? null : $email;
        }
    }

    return $row;
}


    protected function listColumnsMySql(ConnectionInterface $mysql, string $table): array
    {
        $db = $mysql->getDatabaseName();
        $rows = $mysql->select(<<<SQL
            SELECT COLUMN_NAME
            FROM information_schema.columns
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        SQL, [$db, $table]);

        return collect($rows)->pluck('COLUMN_NAME')->filter(fn ($c) => is_string($c))->values()->all();
    }

    protected function listColumnsPg(ConnectionInterface $pg, string $table): array
    {
        $schema = $pg->selectOne('SELECT current_schema() AS s');
        $schemaName = is_object($schema) ? ($schema->s ?? 'public') : 'public';

        $rows = $pg->select(<<<SQL
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ?
            ORDER BY ordinal_position
        SQL, [$schemaName, $table]);

        return collect($rows)->pluck('column_name')->filter(fn ($c) => is_string($c))->values()->all();
    }

    /**
     * Get Postgres column limits (character_maximum_length) keyed by column name.
     */
    protected function getPgColumnLimits(ConnectionInterface $pg, string $table): array
    {
        $schema = $pg->selectOne('SELECT current_schema() AS s');
        $schemaName = is_object($schema) ? ($schema->s ?? 'public') : 'public';

        $rows = $pg->select(<<<SQL
            SELECT column_name, data_type, character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ?
        SQL, [$schemaName, $table]);

        $limits = [];
        foreach ($rows as $row) {
            $col = is_object($row) ? ($row->column_name ?? null) : ($row['column_name'] ?? null);
            $type = is_object($row) ? ($row->data_type ?? null) : ($row['data_type'] ?? null);
            $len = is_object($row) ? ($row->character_maximum_length ?? null) : ($row['character_maximum_length'] ?? null);
            if (is_string($col) && is_string($type)) {
                $limits[$col] = [
                    'type' => $type,
                    'max' => is_numeric($len) ? (int) $len : null,
                ];
            }
        }

        return $limits;
    }

    /**
     * Apply Postgres column length limits: truncate strings that exceed varchar limits.
     */
    protected bool $allowTruncation = false;
    protected bool $usersSkipDuplicateEmails = false;

    protected function applyColumnLimits(array $row, array $limits, string $table): array
    {
        foreach ($limits as $col => $meta) {
            if (!array_key_exists($col, $row)) {
                continue;
            }
            $max = $meta['max'] ?? null;
            $type = $meta['type'] ?? '';
            if ($max !== null && is_string($row[$col])) {
                // Use mbstring if available; fallback to substr
                $value = $row[$col];
                $len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
                if ($len > $max) {
                    if (!$this->allowTruncation) {
                        $idText = array_key_exists('id', $row) ? (' id='.$row['id']) : '';
                        throw new \RuntimeException(
                            "Overflow detected: table={$table} column={$col} max={$max} actual={$len}{$idText}"
                        );
                    }
                    // Truncate only when explicitly allowed
                    if (function_exists('mb_substr')) {
                        $row[$col] = mb_substr($value, 0, $max, 'UTF-8');
                    } else {
                        $row[$col] = substr($value, 0, $max);
                    }
                }
            }
        }

        return $row;
    }

    /**
     * Per-table row normalization.
     */
    protected function postProcessRow(array $row, string $table): array
    {
        if ($table === 'users') {
            if (array_key_exists('email', $row)) {
                $email = is_null($row['email']) ? null : trim((string) $row['email']);
                $row['email'] = ($email === '') ? null : $email;
            }
        }
        return $row;
    }

    /**
     * Insert rows with optional per-table conflict handling.
     */
    protected function insertRows(ConnectionInterface $pg, string $table, array $columns, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        if ($table === 'users' && $this->usersSkipDuplicateEmails && in_array('email', $columns, true)) {
            $this->insertWithConflictSkip($pg, $table, $columns, $rows, ['email']);
            return;
        }

        $pg->table($table)->insert($rows);
    }

    /**
     * Batch INSERT ... ON CONFLICT (cols) DO NOTHING
     */
    protected function insertWithConflictSkip(ConnectionInterface $pg, string $table, array $columns, array $rows, array $conflictColumns): void
    {
        // Build parameterized query
        $columnList = implode(', ', array_map(fn ($c) => '"'.str_replace('"', '""', $c).'"', $columns));
        $placeholders = [];
        $bindings = [];
        foreach ($rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $col) {
                $rowPlaceholders[] = '?';
                $bindings[] = $row[$col] ?? null;
            }
            $placeholders[] = '('.implode(', ', $rowPlaceholders).')';
        }

        $conflictList = implode(', ', array_map(fn ($c) => '"'.str_replace('"', '""', $c).'"', $conflictColumns));
        $sql = 'INSERT INTO "'.str_replace('"', '""', $table).'" ('.$columnList.') VALUES '.implode(', ', $placeholders).' ON CONFLICT ('.$conflictList.') DO NOTHING';

        $pg->statement($sql, $bindings);
    }

    protected function tableHasColumn(ConnectionInterface $conn, string $table, string $column): bool
    {
        $columns = $this->listColumnsMySql($conn, $table);
        return in_array($column, $columns, true);
    }

    protected function truncatePgTable(ConnectionInterface $pg, string $table): void
    {
        // Use CASCADE to handle FKs; rely on disabling triggers afterwards for safety
        $pg->unprepared('TRUNCATE TABLE "'.str_replace('"', '""', $table).'" RESTART IDENTITY CASCADE');
    }

    protected function setPgTriggers(ConnectionInterface $pg, bool $disable): void
    {
        $role = $disable ? 'replica' : 'origin';
        $pg->unprepared("SET session_replication_role = '{$role}'");
    }

    protected function resetPgSequences(ConnectionInterface $pg): void
    {
        $sql = <<<'SQL'
DO $$
DECLARE r record;
BEGIN
  FOR r IN
    SELECT c.relname AS seq, t.relname AS tbl, a.attname AS col
    FROM pg_class c
    JOIN pg_depend d ON d.objid = c.oid AND d.deptype = 'a'
    JOIN pg_class t ON d.refobjid = t.oid
    JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
    WHERE c.relkind = 'S'
  LOOP
    EXECUTE format('SELECT setval(%L, COALESCE((SELECT MAX(%I) FROM %I), 1))', r.seq, r.col, r.tbl);
  END LOOP;
END $$;
SQL;
        $pg->unprepared($sql);
    }

    protected function parseCsvOption(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}