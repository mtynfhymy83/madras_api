<?php
/**
 * Factors → Transactions Migration
 * انتقال ci_factors (MySQL) به transactions (PostgreSQL)
 * ساختار transactions مطابق ci_factors است.
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

echo "🚀 Factors → Transactions Migration (ci_factors = transactions)\n";
echo "================================================================\n\n";

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

    // اگر جدول با ساختار قدیمی (order_id, amount, ...) وجود داشت، حذف و ایجاد مجدد
    $cols = @$pg->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'transactions'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (in_array('order_id', $cols) || in_array('amount', $cols)) {
        echo "   ⚠️  Old transactions schema detected. Dropping and recreating...\n";
        $pg->exec("DROP TABLE IF EXISTS transactions CASCADE;");
    }

    // ایجاد جدول transactions (ساختار مطابق ci_factors)
    $pg->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id BIGINT PRIMARY KEY,
            user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
            status SMALLINT NULL,
            state VARCHAR(1000) NULL,
            cprice INTEGER NULL,
            price INTEGER NULL,
            discount SMALLINT NOT NULL DEFAULT 0,
            discount_id INTEGER NULL,
            paid INTEGER NOT NULL DEFAULT 0,
            ref_id VARCHAR(255) NULL,
            cdate INTEGER NULL,
            pdate INTEGER NULL,
            owner INTEGER NOT NULL,
            section VARCHAR(255) NOT NULL,
            data_id VARCHAR(255) NOT NULL
        );
    ");
    $pg->exec("CREATE SEQUENCE IF NOT EXISTS transactions_id_seq OWNED BY transactions.id;");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_transactions_ref ON transactions(ref_id);");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id);");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_transactions_cdate ON transactions(cdate);");
    try {
        $pg->exec("ALTER TABLE transactions ALTER COLUMN id SET DEFAULT nextval('transactions_id_seq');");
    } catch (Throwable $e) {
        // ممکن است از قبل تنظیم شده باشد
    }
    echo "   ✅ transactions table ready\n\n";

    // نقشه user_id قدیم → جدید
    $userMap = [];
    $stmt = $pg->query("SELECT id, old_id FROM users WHERE old_id IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userMap[(int)$row['old_id']] = (int)$row['id'];
    }
    echo "📊 Loaded " . count($userMap) . " user id mappings\n\n";

    $total = (int)$mysql->query("SELECT COUNT(*) FROM ci_factors")->fetchColumn();
    echo "📋 Total rows in ci_factors: $total\n\n";

    if ($total === 0) {
        echo "⚠️  No data to migrate.\n";
        exit(0);
    }

    $insertSql = "
        INSERT INTO transactions (id, user_id, status, state, cprice, price, discount, discount_id, paid, ref_id, cdate, pdate, owner, section, data_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT (id) DO UPDATE SET
            user_id = EXCLUDED.user_id,
            status = EXCLUDED.status,
            state = EXCLUDED.state,
            cprice = EXCLUDED.cprice,
            price = EXCLUDED.price,
            discount = EXCLUDED.discount,
            discount_id = EXCLUDED.discount_id,
            paid = EXCLUDED.paid,
            ref_id = EXCLUDED.ref_id,
            cdate = EXCLUDED.cdate,
            pdate = EXCLUDED.pdate,
            owner = EXCLUDED.owner,
            section = EXCLUDED.section,
            data_id = EXCLUDED.data_id
    ";
    $insert = $pg->prepare($insertSql);

    $offset = 0;
    $migrated = 0;
    $skipped = 0;

    echo "⚙️  Migrating in chunks of $chunkSize...\n\n";

    while ($offset < $total) {
        $limit = (int)$chunkSize;
        $off = (int)$offset;
        $stmt = $mysql->query("
            SELECT id, user_id, status, state, cprice, price, discount, discount_id, paid, ref_id, cdate, pdate, owner, section, data_id
            FROM ci_factors
            ORDER BY id
            LIMIT $limit OFFSET $off
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userId = null;
            if ($row['user_id'] !== null && $row['user_id'] !== '') {
                $userId = $userMap[(int)$row['user_id']] ?? null;
            }

            $id     = (int)$row['id'];
            $status = $row['status'] !== null && $row['status'] !== '' ? (int)$row['status'] : null;
            $state  = $row['state'] ?? null;
            $cprice = $row['cprice'] !== null && $row['cprice'] !== '' ? (int)$row['cprice'] : null;
            $price  = $row['price'] !== null && $row['price'] !== '' ? (int)$row['price'] : null;
            $discount  = (int)($row['discount'] ?? 0);
            $discountId = $row['discount_id'] !== null && $row['discount_id'] !== '' ? (int)$row['discount_id'] : null;
            $paid   = (int)($row['paid'] ?? 0);
            $refId  = $row['ref_id'] ?? null;
            $cdate  = $row['cdate'] !== null && $row['cdate'] !== '' ? (int)$row['cdate'] : null;
            $pdate  = $row['pdate'] !== null && $row['pdate'] !== '' ? (int)$row['pdate'] : null;
            $owner  = (int)$row['owner'];
            $section = (string)($row['section'] ?? '');
            $dataId  = (string)($row['data_id'] ?? '');

            try {
                $insert->execute([
                    $id,
                    $userId,
                    $status,
                    $state,
                    $cprice,
                    $price,
                    $discount,
                    $discountId,
                    $paid,
                    $refId,
                    $cdate,
                    $pdate,
                    $owner,
                    $section,
                    $dataId,
                ]);
                $migrated++;
            } catch (Throwable $e) {
                $skipped++;
                if ($skipped <= 5) {
                    echo "   ⚠️  skip id=$id: " . $e->getMessage() . "\n";
                }
            }
        }

        $offset += $chunkSize;
        $pct = $total > 0 ? round(min(100, 100 * $offset / $total), 1) : 100;
        echo "   … processed $offset / $total ($pct%) migrated=$migrated\r";
    }

    // به‌روزرسانی sequence برای درج‌های بعدی
    $pg->exec("SELECT setval('transactions_id_seq', (SELECT COALESCE(MAX(id), 1) FROM transactions), true);");
    echo "\n   ✅ Sequence updated\n";

    echo "\n================================================================\n";
    echo "✅ Migration finished\n";
    echo "   Migrated: $migrated\n";
    echo "   Skipped:  $skipped\n";
    $finalCount = (int)$pg->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    echo "📊 Total rows in transactions: $finalCount\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
