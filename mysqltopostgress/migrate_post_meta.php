<?php
/**
 * Post Meta Migration Script
 * Migrate ci_post_meta (MySQL) → product_meta (PostgreSQL)
 * post_id maps to product_id via products.old_id
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
    'host' => $env('MYSQL_HOST', 'localhost'),
    'port' => (int)$env('MYSQL_PORT', '3306'),
    'dbname' => $env('MYSQL_DATABASE', 'madras'),
    'user' => $env('MYSQL_USERNAME', 'root'),
    'pass' => $env('MYSQL_PASSWORD', 'pass')
];

$pgConfig = [
    'host' => $env('DB_HOST', 'localhost'),
    'port' => (int)$env('DB_PORT', '5432'),
    'dbname' => $env('DB_NAME', 'madras'),
    'user' => $env('DB_USERNAME', 'myuser'),
    'pass' => $env('DB_PASSWORD', 'mypass')
];

$chunkSize = 1000;

echo "🚀 Post Meta Migration (ci_post_meta → product_meta)\n";
echo "====================================================\n\n";

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

    // Ensure product_meta table exists
    $pg->exec("
        CREATE TABLE IF NOT EXISTS product_meta (
            id BIGSERIAL PRIMARY KEY,
            product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            meta_key VARCHAR(200) NOT NULL,
            meta_value TEXT,
            UNIQUE (product_id, meta_key)
        )
    ");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_product_meta_product ON product_meta(product_id)");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_product_meta_key ON product_meta(meta_key)");
    echo "📋 Table product_meta ready\n\n";

    // Build post_id (old) → product_id (new) map
    echo "🗺  Building post_id → product_id map... ";
    $stmtMap = $pg->query("SELECT old_id, id FROM products WHERE old_id IS NOT NULL");
    $postToProduct = [];
    while ($row = $stmtMap->fetch(PDO::FETCH_NUM)) {
        $postToProduct[(int)$row[0]] = (int)$row[1];
    }
    echo count($postToProduct) . " products\n\n";

    $totalMeta = (int)$mysql->query("SELECT COUNT(*) FROM ci_post_meta")->fetchColumn();
    echo "📊 Found $totalMeta rows in ci_post_meta\n\n";

    if ($totalMeta === 0) {
        echo "⚠️  No meta rows to migrate\n";
        exit(0);
    }

    $stmtInsert = $pg->prepare("
        INSERT INTO product_meta (product_id, meta_key, meta_value)
        VALUES (:product_id, :meta_key, :meta_value)
        ON CONFLICT (product_id, meta_key) DO UPDATE SET meta_value = EXCLUDED.meta_value
    ");

    $offset = 0;
    $migrated = 0;
    $skipped = 0;

    echo "⚙️  Migrating post meta...\n";

    while ($offset < $totalMeta) {
        $rows = $mysql->query("SELECT id, post_id, meta_key, meta_value FROM ci_post_meta ORDER BY id LIMIT $chunkSize OFFSET $offset")->fetchAll();

        foreach ($rows as $row) {
            $postId = (int)$row['post_id'];
            if (!isset($postToProduct[$postId])) {
                $skipped++;
                continue;
            }
            $productId = $postToProduct[$postId];
            try {
                $stmtInsert->execute([
                    'product_id' => $productId,
                    'meta_key' => $row['meta_key'] ?? '',
                    'meta_value' => $row['meta_value'] ?? null
                ]);
                $migrated++;
            } catch (PDOException $e) {
                $skipped++;
            }
        }

        $offset += $chunkSize;
        $percent = min(100, round(($offset / $totalMeta) * 100));
        echo "\r   Progress: $migrated migrated, $skipped skipped ($percent%)      ";
    }

    echo "\n\n=============================\n";
    echo "✅ Migration completed!\n";
    echo "   Migrated: $migrated rows\n";
    echo "   Skipped (no product): $skipped rows\n";

    $pgCount = (int)$pg->query("SELECT COUNT(*) FROM product_meta")->fetchColumn();
    echo "\n📊 Total rows in product_meta: $pgCount\n";
    echo "\n🎉 Done!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
