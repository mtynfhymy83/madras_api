<?php
/**
 * ci_discounts → coupons و ci_discount_used → discount_used
 * قبل از اجرا: transactions و users باید از قبل مایگریت شده باشند.
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

echo "🚀 Discounts Migration (ci_discounts → coupons, ci_discount_used → discount_used)\n";
echo "===============================================================================\n\n";

try {
    $mysql = new PDO(
        "mysql:host={$mysqlConfig['host']};port={$mysqlConfig['port']};dbname={$mysqlConfig['dbname']};charset=utf8mb4",
        $mysqlConfig['user'],
        $mysqlConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "📡 MySQL connected\n";

    $pg = new PDO(
        "pgsql:host={$pgConfig['host']};port={$pgConfig['port']};dbname={$pgConfig['dbname']}",
        $pgConfig['user'],
        $pgConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "📡 PostgreSQL connected\n\n";

    // اگر coupons با ساختار قدیمی وجود داشت
    $cols = @$pg->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'coupons'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (in_array('discount_type', $cols) || in_array('discount_value', $cols)) {
        echo "⚠️  Old coupons schema detected. Dropping...\n";
        $pg->exec("DROP TABLE IF EXISTS discount_used CASCADE;");
        $pg->exec("DROP TABLE IF EXISTS coupons CASCADE;");
    }

    // ایجاد coupons (مطابق ci_discounts)
    $pg->exec("
        CREATE TABLE IF NOT EXISTS coupons (
            id BIGINT PRIMARY KEY,
            code VARCHAR(255) NULL,
            percent SMALLINT NULL,
            price VARCHAR(255) NULL,
            category_id INTEGER NULL,
            used INT NOT NULL DEFAULT 0,
            factor_id BIGINT NULL,
            cdate INTEGER NULL,
            udate INTEGER NULL,
            expdate INTEGER NULL,
            maxallow INT NOT NULL DEFAULT 0,
            fee INT NOT NULL DEFAULT 0,
            bookid INT NOT NULL DEFAULT 0,
            author INT NOT NULL DEFAULT 0
        );
    ");
    $pg->exec("CREATE SEQUENCE IF NOT EXISTS coupons_id_seq OWNED BY coupons.id;");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_coupons_code ON coupons(code);");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_coupons_category ON coupons(category_id);");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_coupons_expdate ON coupons(expdate);");
    echo "✅ coupons table ready\n";

    // ایجاد discount_used (مطابق ci_discount_used)
    $pg->exec("
        CREATE TABLE IF NOT EXISTS discount_used (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            discount_id BIGINT NOT NULL REFERENCES coupons(id) ON DELETE CASCADE,
            udate INTEGER NOT NULL,
            factor_id BIGINT NOT NULL REFERENCES transactions(id) ON DELETE CASCADE
        );
    ");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_discount_used_user ON discount_used(user_id);");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_discount_used_discount ON discount_used(discount_id);");
    $pg->exec("CREATE INDEX IF NOT EXISTS idx_discount_used_factor ON discount_used(factor_id);");
    echo "✅ discount_used table ready\n\n";

    // نقشه user_id قدیم → جدید
    $userMap = [];
    $stmt = $pg->query("SELECT id, old_id FROM users WHERE old_id IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userMap[(int)$row['old_id']] = (int)$row['id'];
    }
    echo "📊 Loaded " . count($userMap) . " user mappings\n";

    // --- مایگریشن ci_discounts → coupons ---
    $totalCoupons = (int)$mysql->query("SELECT COUNT(*) FROM ci_discounts")->fetchColumn();
    echo "📋 ci_discounts rows: $totalCoupons\n";

    $ins = $pg->prepare("
        INSERT INTO coupons (id, code, percent, price, category_id, used, factor_id, cdate, udate, expdate, maxallow, fee, bookid, author)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT (id) DO UPDATE SET
            code = EXCLUDED.code, percent = EXCLUDED.percent, price = EXCLUDED.price,
            category_id = EXCLUDED.category_id, used = EXCLUDED.used, factor_id = EXCLUDED.factor_id,
            cdate = EXCLUDED.cdate, udate = EXCLUDED.udate, expdate = EXCLUDED.expdate,
            maxallow = EXCLUDED.maxallow, fee = EXCLUDED.fee, bookid = EXCLUDED.bookid, author = EXCLUDED.author
    ");

    $stmt = $mysql->query("SELECT id, code, percent, price, category_id, used, factor_id, cdate, udate, expdate, maxallow, fee, bookid, author FROM ci_discounts ORDER BY id");
    $c = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ins->execute([
            (int)$row['id'],
            $row['code'] ?? null,
            $row['percent'] !== null && $row['percent'] !== '' ? (int)$row['percent'] : null,
            $row['price'] ?? null,
            $row['category_id'] !== null && $row['category_id'] !== '' ? (int)$row['category_id'] : null,
            (int)($row['used'] ?? 0),
            $row['factor_id'] !== null && $row['factor_id'] !== '' ? (int)$row['factor_id'] : null,
            $row['cdate'] !== null && $row['cdate'] !== '' ? (int)$row['cdate'] : null,
            $row['udate'] !== null && $row['udate'] !== '' ? (int)$row['udate'] : null,
            $row['expdate'] !== null && $row['expdate'] !== '' ? (int)$row['expdate'] : null,
            (int)($row['maxallow'] ?? 0),
            (int)($row['fee'] ?? 0),
            (int)($row['bookid'] ?? 0),
            (int)($row['author'] ?? 0),
        ]);
        $c++;
    }
    echo "✅ Coupons migrated: $c\n\n";

    // --- مایگریشن ci_discount_used → discount_used ---
    // factor_id در PG همان transactions.id است (از مایگریشن factors). user_id را map می‌کنیم.
    $totalUsed = (int)$mysql->query("SELECT COUNT(*) FROM ci_discount_used")->fetchColumn();
    echo "📋 ci_discount_used rows: $totalUsed\n";

    $insUsed = $pg->prepare("
        INSERT INTO discount_used (user_id, discount_id, udate, factor_id)
        VALUES (?, ?, ?, ?)
    ");

    $chkTx = $pg->prepare("SELECT 1 FROM transactions WHERE id = ?");
    $chkCoupon = $pg->prepare("SELECT 1 FROM coupons WHERE id = ?");

    $stmt = $mysql->query("SELECT id, user_id, discount_id, udate, factor_id FROM ci_discount_used ORDER BY id");
    $u = 0;
    $skipped = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $newUserId = $userMap[(int)$row['user_id']] ?? null;
        if ($newUserId === null) {
            $skipped++;
            continue;
        }
        $factorId = (int)$row['factor_id'];
        $discountId = (int)$row['discount_id'];

        $chkTx->execute([$factorId]);
        if (!$chkTx->fetch()) {
            $skipped++;
            continue;
        }
        $chkCoupon->execute([$discountId]);
        if (!$chkCoupon->fetch()) {
            $skipped++;
            continue;
        }
        try {
            $insUsed->execute([
                $newUserId,
                $discountId,
                (int)$row['udate'],
                $factorId,
            ]);
            $u++;
        } catch (Throwable $e) {
            $skipped++;
        }
    }
    echo "✅ discount_used migrated: $u (skipped: $skipped)\n";

    $pg->exec("SELECT setval('coupons_id_seq', (SELECT COALESCE(MAX(id), 1) FROM coupons), true);");
    echo "\n✅ Sequence coupons_id_seq updated\n";
    echo "\n===============================================================================\n";
    echo "Done.\n";

} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
