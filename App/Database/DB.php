<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

class DB
{
    private static ?PDOPool $pool = null;

    public static function init(PDOPool $pool): void
    {
        self::$pool = $pool;
    }

    /**
     * دریافت آمار Pool (برای مانیتورینگ)
     */
    public static function getPoolStats(): ?array
    {
        if (!self::$pool) {
            return null;
        }
        return self::$pool->getStats();
    }

    /**
     * دریافت یک اتصال از Pool (بدون اجرای callback)
     */
    public static function get(): PDO
    {
        if (!self::$pool) {
            throw new \RuntimeException("Database Pool not initialized!");
        }
        return self::$pool->get();
    }

    /**
     * اجرای یک عملیات با دریافت اتصال مدیریت شده
     */
    public static function run(callable $callback): mixed
    {
        if (!self::$pool) {
            throw new \RuntimeException("Database Pool not initialized!");
        }

        $pdo = self::$pool->get();
        try {
            return $callback($pdo);
        } finally {
            self::$pool->put($pdo);
        }
    }

    // ---------------- HELPER METHODS ----------------

    /**
     * اجرای کوئری SELECT و دریافت همه نتایج
     */
    public static function fetchAll(string $query, array $params = []): array
    {
        $result = self::run(function (PDO $pdo) use ($query, $params) {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        });
        return $result;
    }

    /**
     * اجرای کوئری SELECT و دریافت یک سطر
     */
    public static function fetch(string $query, array $params = [])
    {
        $result = self::run(function (PDO $pdo) use ($query, $params) {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        });
        return $result;
    }

    /**
     * اجرای کوئری‌های INSERT, UPDATE, DELETE
     * @return bool|string برگرداندن true یا ID رکورد درج شده
     */
    public static function execute(string $query, array $params = []): mixed
    {
        $result = self::run(function (PDO $pdo) use ($query, $params) {
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute($params);

            // اگر Insert بود، ID را برگردان
            if (str_starts_with(strtoupper(trim($query)), 'INSERT')) {
                // برای Postgres ممکن است نیاز به RETURNING id در کوئری داشته باشید
                // اما lastInsertId هم معمولا کار می‌کند
                return $pdo->lastInsertId() ?: $result;
            }

            return $result;
        });
        return $result;
    }

    /**
     * مدیریت خودکار تراکنش (Transaction)
     * اگر خطا رخ دهد، خودکار Rollback می‌کند.
     */
    public static function transaction(callable $callback): mixed
    {
        return self::run(function (PDO $pdo) use ($callback) {
            try {
                $pdo->beginTransaction();
                $result = $callback($pdo);
                $pdo->commit();
                return $result;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e; // خطا را دوباره پرتاب کن تا کنترلر بفهمد
            }
        });
    }

}
