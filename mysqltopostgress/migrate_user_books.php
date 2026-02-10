<?php
/**
 * User Books Migration Script
 * Migrate ci_user_books (MySQL) → user_library (PostgreSQL)
 *
 * Mapping:
 *   ci_user_books.user_id  → users.id (via users.old_id)
 *   ci_user_books.book_id  → products.id (via products.old_id AND type='book')
 *   ci_user_books.expiremembership → user_library.expires_at
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->load();
}

$env = fn(string $key, $default = '') => $_ENV[$key] ?? getenv($key) ?: $default;

$mysqlConfig = [
    'host'   => $env('MYSQL_HOST', 'localhost'),
    'port'   => (int)$env('MYSQL_PORT', '3306'),
    'dbname' => $env('MYSQL_DATABASE', 'madras'),
    'user'   => $env('MYSQL_USERNAME', 'root'),
    'pass'   => $env('MYSQL_PASSWORD', 'pass'),
];

$pgConfig = [
    'host'   => $env('DB_HOST', 'localhost'),
    'port'   => (int)$env('DB_PORT', '5432'),
    'dbname' => $env('DB_NAME', 'madras'),
    'user'   => $env('DB_USERNAME', 'myuser'),
    'pass'   => $env('DB_PASSWORD', 'mypass'),
];

$chunkSize = (int)($env('MIGRATE_CHUNK_SIZE', '3000'));

echo "🚀 User Books Migration (ci_user_books → user_library)\n";
echo "======================================================\n\n";

try {
    echo "📡 Connecting to MySQL...\n";
    $mysql = new PDO(
        "mysql:host={$mysqlConfig['host']};port={$mysqlConfig['port']};dbname={$mysqlConfig['dbname']};charset=utf8mb4",
        $mysqlConfig['user'],
        $mysqlConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "   ✅ MySQL connected\n";

    echo "📡 Connecting to PostgreSQL...\n";
    $pg = new PDO(
        "pgsql:host={$pgConfig['host']};port={$pgConfig['port']};dbname={$pgConfig['dbname']}",
        $pgConfig['user'],
        $pgConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "   ✅ PostgreSQL connected\n\n";

    // Ensure user_library exists and has required columns
    $pg->exec("
        CREATE TABLE IF NOT EXISTS user_library (
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            obtained_at TIMESTAMPTZ DEFAULT NOW(),
            source VARCHAR(20) DEFAULT 'purchase',
            expires_at TIMESTAMPTZ,
            PRIMARY KEY (user_id, product_id)
        );
    ");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_user_library_user ON user_library(user_id);");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_user_library_product ON user_library(product_id);");
    echo "   ✅ user_library table/indexes ready\n\n";

    // Build lookup: old user_id -> new users.id
    $userMap = [];
    $stmt = $pg->query("SELECT id, old_id FROM users WHERE old_id IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userMap[(int)$row['old_id']] = (int)$row['id'];
    }
    echo "📊 Loaded " . count($userMap) . " user id mappings (old_id → id)\n";

    // Build lookup: old book_id -> new products.id (type=book)
    $productMap = [];
    $stmt = $pg->query("SELECT id, old_id FROM products WHERE type = 'book' AND old_id IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productMap[(int)$row['old_id']] = (int)$row['id'];
    }
    echo "📊 Loaded " . count($productMap) . " product id mappings (old book_id → product_id)\n\n";

    $total = (int)$mysql->query("SELECT COUNT(*) FROM ci_user_books")->fetchColumn();
    echo "📋 Total rows in ci_user_books: $total\n\n";

    if ($total === 0) {
        echo "⚠️  No data to migrate.\n";
        exit(0);
    }

    $offset = 0;
    $migrated = 0;
    $skipped = 0;
    $errors = [];

    echo "⚙️  Migrating in chunks of $chunkSize...\n\n";

    while ($offset < $total) {
        $limit = (int)$chunkSize;
        $off = (int)$offset;
        $stmt = $mysql->query("
            SELECT id, user_id, book_id, factor_id, need_update, expiremembership
            FROM ci_user_books
            ORDER BY id
            LIMIT $limit OFFSET $off
        ");

        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oldUserId = $row['user_id'] !== null ? (int)$row['user_id'] : null;
            $oldBookId = $row['book_id'] !== null ? (int)$row['book_id'] : null;

            if ($oldUserId === null || $oldBookId === null) {
                $skipped++;
                continue;
            }

            $newUserId = $userMap[$oldUserId] ?? null;
            $newProductId = $productMap[$oldBookId] ?? null;

            if ($newUserId === null) {
                $skipped++;
                $errors["user_not_found:{$oldUserId}"] = ($errors["user_not_found:{$oldUserId}"] ?? 0) + 1;
                continue;
            }
            if ($newProductId === null) {
                $skipped++;
                $errors["product_not_found:{$oldBookId}"] = ($errors["product_not_found:{$oldBookId}"] ?? 0) + 1;
                continue;
            }

            $expiresAt = null;
            if (!empty($row['expiremembership'])) {
                $d = $row['expiremembership'];
                $expiresAt = (strlen($d) === 10) ? $d . ' 23:59:59' : $d;
            }

            $batch[] = [$newUserId, $newProductId, $expiresAt];
        }

        if (count($batch) > 0) {
            $values = [];
            $params = [];
            $i = 0;
            foreach ($batch as $r) {
                $values[] = "(\$" . (++$i) . ", \$" . (++$i) . ", NOW(), 'purchase', \$" . (++$i) . ")";
                $params[] = $r[0];
                $params[] = $r[1];
                $params[] = $r[2];
            }
            $sql = "
                INSERT INTO user_library (user_id, product_id, obtained_at, source, expires_at)
                VALUES " . implode(", ", $values) . "
                ON CONFLICT (user_id, product_id) DO UPDATE SET expires_at = EXCLUDED.expires_at
            ";
            try {
                $pg->prepare($sql)->execute($params);
                $migrated += count($batch);
            } catch (\Throwable $e) {
                $insertOne = $pg->prepare("
                    INSERT INTO user_library (user_id, product_id, obtained_at, source, expires_at)
                    VALUES (?, ?, NOW(), 'purchase', ?)
                    ON CONFLICT (user_id, product_id) DO UPDATE SET expires_at = EXCLUDED.expires_at
                ");
                foreach ($batch as $r) {
                    try {
                        $insertOne->execute([$r[0], $r[1], $r[2]]);
                        $migrated++;
                    } catch (\Throwable $ex) {
                        $skipped++;
                    }
                }
            }
        }

        $offset += $chunkSize;
        $pct = $total > 0 ? round(min(100, 100 * $offset / $total), 1) : 100;
        echo "   … processed $offset / $total ($pct%) migrated=$migrated\r";
    }

    echo "\n\n======================================================\n";
    echo "✅ Migration finished\n";
    echo "   Migrated: $migrated\n";
    echo "   Skipped:  $skipped\n";

    if (!empty($errors)) {
        echo "\n⚠️  Error summary (first 15):\n";
        $i = 0;
        foreach ($errors as $msg => $count) {
            if ($i++ >= 15) {
                echo "   ... and " . (count($errors) - 15) . " more\n";
                break;
            }
            echo "   $msg => $count\n";
        }
    }

    $finalCount = (int)$pg->query("SELECT COUNT(*) FROM user_library")->fetchColumn();
    echo "\n📊 Total rows in user_library: $finalCount\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
