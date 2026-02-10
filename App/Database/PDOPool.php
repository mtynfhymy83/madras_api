<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine;

class PDOPool
{
    private Channel $pool;
    private array $config;
    private int $statementTimeoutMs;
    private int $maxIdleTime; // seconds

    /**
     * @param int $size Number of connections in the pool
     */
    public function __construct(int $size = 10)
    {
        $this->pool = new Channel($size);

        $host = $this->env('DB_HOST', '');
        $port = $this->env('DB_PORT', '5432');
        $dbname = $this->env('DB_NAME', '');
        $user = $this->env('DB_USERNAME', '');
        $pass = $this->env('DB_PASSWORD', '');

        if ($host === '' || $dbname === '' || $user === '') {
            throw new \RuntimeException(
                'Database config missing. Set DB_HOST, DB_NAME, DB_USERNAME (and optionally DB_PORT, DB_PASSWORD) in .env or environment.'
            );
        }

        // ✅ FIX: Use Swoole's coroutine-safe DNS resolution
        $resolvedHost = $host;
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            // Only resolve if it's not already an IP address
            if (Coroutine::getCid() > 0) {
                // We're in a coroutine context, use async DNS
                $resolved = Coroutine\System::gethostbyname($host, AF_INET, 2.0);
                if ($resolved !== false && $resolved !== $host) {
                    $resolvedHost = $resolved;
                } else {
                    error_log("⚠️ DNS resolution failed for DB_HOST: $host, using hostname");
                }
            } else {
                // Fallback to blocking DNS (constructor might be called outside coroutine)
                $resolved = gethostbyname($host);
                if ($resolved !== $host) {
                    $resolvedHost = $resolved;
                } else {
                    error_log("⚠️ DNS resolution failed for DB_HOST: $host");
                }
            }
        }
        
        $this->maxIdleTime = max(0, (int)$this->env('DB_MAX_IDLE_TIME', '600'));

        // ✅ Validate statement timeout
        $this->statementTimeoutMs = max(0, min(3600000, (int)$this->env('DB_STATEMENT_TIMEOUT_MS', '3000')));

        $this->config = [
            'dsn' => sprintf(
                "pgsql:host=%s;port=%s;dbname=%s;connect_timeout=3",
                $resolvedHost,
                $port,
                $dbname
            ),
            'user' => $user,
            'pass' => $pass,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false, // ✅ Critical for Swoole
                PDO::ATTR_TIMEOUT => 3,
            ]
        ];

        // Initialize connection pool with retry mechanism
        $maxRetries = 3;
        $retryDelay = 1; // seconds
        
        for ($i = 0; $i < $size; $i++) {
            $connection = null;
            $attempt = 0;
            
            while ($attempt < $maxRetries && $connection === null) {
                try {
                    $connection = $this->makeConnection();
                } catch (\Throwable $e) {
                    $attempt++;
                    if ($attempt >= $maxRetries) {
                        error_log("❌ Failed to create connection after $maxRetries attempts: " . $e->getMessage());
                        // Push null instead of crashing - pool will handle it in get()
                        $connection = null;
                        break;
                    }
                    error_log("⚠️ Connection attempt $attempt failed, retrying in {$retryDelay}s...");
                    sleep($retryDelay);
                }
            }
            
            $this->pool->push(['pdo' => $connection, 'last_used' => time()]);
        }
    }

    /**
     * Read env var from $_ENV or getenv() (Docker/CLI often only set getenv).
     */
    private function env(string $key, string $default = ''): string
    {
        $v = $_ENV[$key] ?? getenv($key);
        if ($v === false || $v === null) {
            return $default;
        }
        return (string) $v;
    }

    private function makeConnection(): PDO
    {
        try {
            $pdo = new PDO(
                $this->config['dsn'],
                $this->config['user'],
                $this->config['pass'],
                $this->config['options']
            );

            // ✅ FIX: Set statement timeout (PostgreSQL doesn't support prepared statements for SET)
            if ($this->statementTimeoutMs > 0) {
                try {
                    // مقدار را validate می‌کنیم (فقط عدد) و مستقیماً در کوئری قرار می‌دهیم
                    $timeoutValue = (int)$this->statementTimeoutMs;
                    if ($timeoutValue > 0 && $timeoutValue <= 3600000) {
                        // PostgreSQL: statement_timeout به میلی‌ثانیه (مثلاً '3000ms' یا فقط عدد)
                        $pdo->exec("SET statement_timeout = {$timeoutValue}");
                    }
                } catch (\Throwable $e) {
                    error_log("⚠️ Failed to set statement_timeout: " . $e->getMessage());
                }
            }

            return $pdo;
        } catch (PDOException $e) {
            error_log("❌ Failed to create database connection: " . $e->getMessage());
            throw $e;
        }
    }

    public function get(): PDO
    {
        // Wait up to 5 seconds for an available connection (افزایش از 3 به 5 برای ترافیک بالا)
        $pdo = $this->pool->pop(5.0);

        if ($pdo === false) {
            $stats = $this->getStats();
            throw new \RuntimeException(
                sprintf(
                    "Database pool exhausted! All %d connections are in use. " .
                    "Available: %d, In use: %d. Consider increasing DB_POOL_SIZE or optimizing queries.",
                    $stats['capacity'],
                    $stats['available'],
                    $stats['in_use']
                )
            );
        }

        if (!is_array($pdo) || !isset($pdo['pdo']) || !($pdo['pdo'] instanceof PDO)) {
            throw new \RuntimeException("Invalid connection object received from pool.");
        }

        /** @var PDO|null $conn */
        $conn = $pdo['pdo'];
        $lastUsed = (int)($pdo['last_used'] ?? 0);

        if (!$conn instanceof PDO) {
            try {
                $conn = $this->makeConnection();
            } catch (\Throwable $e) {
                // اسلات خالی را برگردان تا pool لیک نشود
                $this->pool->push(['pdo' => null, 'last_used' => time()]);
                throw new \RuntimeException("Failed to create database connection: " . $e->getMessage());
            }
        }

        // فقط اگر مدت زیادی بیکار بوده، سلامت را تست کن
        if ($this->maxIdleTime > 0 && $lastUsed > 0 && (time() - $lastUsed) > $this->maxIdleTime) {
            if (!$this->isConnectionAlive($conn)) {
                error_log("⚠️ Dead connection detected, creating new one");
                try {
                    $conn = $this->makeConnection();
                } catch (\Throwable $e) {
                    // اسلات را خالی ولی سالم برگردان تا pool لیک نشود
                    $this->pool->push(['pdo' => null, 'last_used' => time()]);
                    throw new \RuntimeException("Failed to reconnect to database: " . $e->getMessage());
                }
            }
        }

        return $conn;
    }

    public function put(PDO $pdo): void
    {
        // ✅ Clean up any leftover transactions
        try {
            if ($pdo->inTransaction()) {
                error_log("⚠️ Rolling back uncommitted transaction before returning connection to pool");
                $pdo->rollBack();
            }
        } catch (\Throwable $e) {
            // Connection is broken, create a new one
            error_log("⚠️ Connection error during cleanup: " . $e->getMessage() . ", creating new connection");
            try {
                $pdo = $this->makeConnection();
            } catch (\Throwable $err) {
                // Can't create new connection either - database is down
                error_log("❌ Failed to create replacement connection: " . $err->getMessage());
                // اسلات خالی برگردان تا pool لیک نشود
                $this->pool->push(['pdo' => null, 'last_used' => time()]);
                return;
            }
        }

        $this->pool->push(['pdo' => $pdo, 'last_used' => time()]);
    }

    /**
     * ✅ NEW: Check if connection is still alive
     */
    private function isConnectionAlive(PDO $pdo): bool
    {
        try {
            // Quick ping query
            $stmt = $pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * ✅ NEW: Execute a query with automatic connection management
     * 
     * Usage:
     * $result = $pool->execute(function($pdo) {
     *     return $pdo->query("SELECT * FROM users")->fetchAll();
     * });
     */
    public function execute(callable $callback): mixed
    {
        $pdo = $this->get();
        try {
            return $callback($pdo);
        } finally {
            // ✅ Guaranteed to return connection even if exception occurs
            $this->put($pdo);
        }
    }

    /**
     * ✅ NEW: Execute a transaction with automatic rollback on failure
     * 
     * Usage:
     * $pool->transaction(function($pdo) {
     *     $pdo->exec("INSERT INTO users ...");
     *     $pdo->exec("INSERT INTO logs ...");
     * });
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->get();
        try {
            $pdo->beginTransaction();
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->put($pdo);
        }
    }

    /**
     * Get current pool statistics
     */
    public function getStats(): array
    {
        return [
            'available' => $this->pool->length(),
            'capacity' => $this->pool->capacity,
            'in_use' => $this->pool->capacity - $this->pool->length(),
        ];
    }

    /**
     * Close all connections (useful for graceful shutdown)
     */
    public function close(): void
    {
        $this->pool->close();
    }
}
