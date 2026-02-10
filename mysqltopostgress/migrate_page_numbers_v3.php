<?php
/**
 * Update book_contents.page_number from ci_post_meta.pages
 *
 * Two formats in ci_post_meta.pages:
 *
 * 1) OFFSET format (e.g. book_id=7): "0,3,6,9,10,13,18,..."
 *    = last content index for each page (0-based).
 *    Content at order 0 → page 1, order 1,2,3 → page 2, order 4,5,6 → page 3, ...
 *
 * 2) PHYSICAL PAGE format (e.g. book_id=2): "3,8,10,13,17,21,..."
 *    = actual book page numbers. Use ci_book_meta.page (1,2,3...) as index:
 *    page 1 → pages[0], page 2 → pages[1], ...
 *
 * Detection: if first value is 0 → offset format; else → physical page format.
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

echo "🚀 Update book_contents.page_number (offset + physical formats)\n";
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

    // Load all pages mappings
    echo "📋 Loading pages from ci_post_meta...\n";
    $stmt = $mysql->query("SELECT post_id, meta_value FROM ci_post_meta WHERE meta_key = 'pages'");
    $pageMappings = [];
    while ($row = $stmt->fetch()) {
        $bookId = (int)$row['post_id'];
        $vals = array_values(array_map('intval', array_filter(explode(',', trim((string)$row['meta_value'])), fn($v) => $v !== '')));
        if (empty($vals)) {
            continue;
        }
        $pageMappings[$bookId] = $vals;
    }
    echo "   Found " . count($pageMappings) . " books\n\n";

    $updated = 0;
    $offsetFormatCount = 0;
    $physicalFormatCount = 0;

    echo "⚙️  Updating page numbers (batch per book)...\n";

    foreach ($pageMappings as $bookId => $vals) {
        if (empty($vals)) {
            continue;
        }

        // Clear this book's page_number first to avoid unique constraint
        $pg->prepare("UPDATE book_contents SET page_number = NULL WHERE book_id = ?")->execute([$bookId]);

        $firstVal = $vals[array_key_first($vals)];
        $isOffsetFormat = ($firstVal === 0);

        if ($isOffsetFormat) {
            // OFFSET format: vals = [0, 3, 6, 9, ...] = last index per page
            $offsets = $vals;
            $stmt = $pg->prepare("SELECT id, \"order\" FROM book_contents WHERE book_id = :bid ORDER BY \"order\"");
            $stmt->execute(['bid' => $bookId]);
            $rows = $stmt->fetchAll();

            $ids = [];
            $pns = [];
            foreach ($rows as $row) {
                $orderIndex = (int)$row['order'];
                $pageNum = 1;
                foreach ($offsets as $k => $lastIdx) {
                    if ($lastIdx >= $orderIndex) {
                        $pageNum = $k + 1;
                        break;
                    }
                }
                $ids[] = (int)$row['id'];
                $pns[] = $pageNum;
            }

            if (!empty($ids)) {
                $chunk = 300;
                for ($i = 0; $i < count($ids); $i += $chunk) {
                    $sliceIds = array_slice($ids, $i, $chunk);
                    $slicePns = array_slice($pns, $i, $chunk);
                    $pg->exec("
                        UPDATE book_contents c SET page_number = d.pn
                        FROM (SELECT unnest(ARRAY[" . implode(',', $sliceIds) . "]) AS id, unnest(ARRAY[" . implode(',', $slicePns) . "]) AS pn) d
                        WHERE c.id = d.id
                    ");
                }
                $updated += count($ids);
            }
            $offsetFormatCount++;
        } else {
            // PHYSICAL format
            $stmt = $mysql->prepare("SELECT `order`, `page` FROM ci_book_meta WHERE book_id = :bid ORDER BY `order`");
            $stmt->execute(['bid' => $bookId]);
            $contents = $stmt->fetchAll();

            $stmtPg = $pg->prepare("SELECT id, \"order\" FROM book_contents WHERE book_id = :bid ORDER BY \"order\"");
            $stmtPg->execute(['bid' => $bookId]);
            $pgRows = $stmtPg->fetchAll();

            if (count($contents) !== count($pgRows)) {
                continue;
            }

            $ids = [];
            $pns = [];
            foreach ($pgRows as $idx => $pgRow) {
                $seqPage = (int)($contents[$idx]['page'] ?? 1);
                $pageIndex = $seqPage - 1;
                $actualPage = isset($vals[$pageIndex]) ? $vals[$pageIndex] : null;
                if ($actualPage !== null) {
                    $ids[] = (int)$pgRow['id'];
                    $pns[] = $actualPage;
                }
            }
            if (!empty($ids)) {
                $chunk = 300;
                for ($i = 0; $i < count($ids); $i += $chunk) {
                    $sliceIds = array_slice($ids, $i, $chunk);
                    $slicePns = array_slice($pns, $i, $chunk);
                    $pg->exec("
                        UPDATE book_contents c SET page_number = d.pn
                        FROM (SELECT unnest(ARRAY[" . implode(',', $sliceIds) . "]) AS id, unnest(ARRAY[" . implode(',', $slicePns) . "]) AS pn) d
                        WHERE c.id = d.id
                    ");
                }
                $updated += count($ids);
            }
            $physicalFormatCount++;
        }

        static $n = 0;
        $n++;
        if ($n % 50 === 0) {
            echo "\r   Processed $n/" . count($pageMappings) . " books (offset: $offsetFormatCount, physical: $physicalFormatCount)...";
        }
    }

    echo "\n\n=============================\n";
    echo "✅ Done!\n";
    echo "   Updated: $updated rows\n";
    echo "   Books (offset format): $offsetFormatCount\n";
    echo "   Books (physical format): $physicalFormatCount\n";

    // Verification for book_id=7 (offset format)
    echo "\n📊 Verification book_id=7 (offset format):\n";
    echo "  order | page_number\n";
    echo "  ------|------------\n";
    $stmt = $pg->prepare("SELECT \"order\", page_number FROM book_contents WHERE book_id = 7 ORDER BY \"order\" LIMIT 25");
    $stmt->execute([]);
    while ($row = $stmt->fetch()) {
        echo sprintf("  %5d | %s\n", $row['order'], $row['page_number'] ?? 'NULL');
    }

    // Verification for book_id=2 (physical format)
    echo "\n📊 Verification book_id=2 (physical format):\n";
    echo "  order | page_number\n";
    echo "  ------|------------\n";
    $stmt = $pg->prepare("SELECT \"order\", page_number FROM book_contents WHERE book_id = 2 ORDER BY \"order\" LIMIT 15");
    $stmt->execute([]);
    while ($row = $stmt->fetch()) {
        echo sprintf("  %5d | %s\n", $row['order'], $row['page_number'] ?? 'NULL');
    }

    echo "\n🎉 Done!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
