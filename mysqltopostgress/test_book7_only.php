<?php
/**
 * Test: update page_number only for book_id=7 (offset format)
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env')) {
    Dotenv\Dotenv::createImmutable($projectRoot)->load();
}

$env = fn(string $key, $default = '') => $_ENV[$key] ?? getenv($key) ?: $default;

$mysql = new PDO(
    "mysql:host=" . $env('MYSQL_HOST', 'localhost') . ";dbname=" . $env('MYSQL_DATABASE', 'madras'),
    $env('MYSQL_USERNAME', 'root'),
    $env('MYSQL_PASSWORD', 'pass'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$pg = new PDO(
    "pgsql:host=" . $env('DB_HOST', 'localhost') . ";port=" . $env('DB_PORT', '5432') . ";dbname=" . $env('DB_NAME', 'madras'),
    $env('DB_USERNAME', 'myuser'),
    $env('DB_PASSWORD', 'mypass'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$bookId = 7;

$stmt = $mysql->query("SELECT meta_value FROM ci_post_meta WHERE post_id = $bookId AND meta_key = 'pages'");
$vals = array_map('intval', explode(',', $stmt->fetchColumn()));
echo "Book 7 pages (offsets): " . implode(',', array_slice($vals, 0, 15)) . "...\n";

$pg->exec("UPDATE book_contents SET page_number = NULL WHERE book_id = $bookId");

$stmt = $pg->prepare("SELECT id, \"order\" FROM book_contents WHERE book_id = :bid ORDER BY \"order\"");
$stmt->execute(['bid' => $bookId]);
$rows = $stmt->fetchAll();

$offsets = $vals;
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

echo "First 20: order -> page_number\n";
for ($i = 0; $i < min(20, count($ids)); $i++) {
    echo "  order {$rows[$i]['order']} -> page {$pns[$i]}\n";
}

// Update in chunks of 200
$chunk = 200;
for ($i = 0; $i < count($ids); $i += $chunk) {
    $sliceIds = array_slice($ids, $i, $chunk);
    $slicePns = array_slice($pns, $i, $chunk);
    $pg->exec("
        UPDATE book_contents c SET page_number = d.pn
        FROM (SELECT unnest(ARRAY[" . implode(',', $sliceIds) . "]) AS id, unnest(ARRAY[" . implode(',', $slicePns) . "]) AS pn) d
        WHERE c.id = d.id
    ");
}
echo "Updated " . count($ids) . " rows for book_id=7\n";

// Verify
echo "\nVerification (first 25):\n";
$stmt = $pg->query("SELECT \"order\", page_number FROM book_contents WHERE book_id = 7 ORDER BY \"order\" LIMIT 25");
while ($row = $stmt->fetch()) {
    echo "  order {$row['order']} -> page_number {$row['page_number']}\n";
}

echo "\nExpected: page 1 has 1 content (order 0), page 2 has orders 1,2,3, page 3 has 4,5,6, ...\n";
