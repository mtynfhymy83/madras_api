<?php
/**
 * Check for duplicates and constraints
 */

$pg = new PDO('pgsql:host=localhost;port=5432;dbname=madras', 'myuser', 'mypass');
$pg->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== PostgreSQL Constraints ===\n\n";

$stmt = $pg->query("SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'book_contents'");
$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    echo $row['indexname'] . ":\n  " . $row['indexdef'] . "\n\n";
}

echo "=== Duplicates in MySQL ===\n\n";

$mysql = new PDO('mysql:host=localhost;port=3306;dbname=madras;charset=utf8mb4', 'root', 'pass');
$mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $mysql->query("SELECT book_id, page, paragraph, COUNT(*) as cnt FROM ci_book_meta GROUP BY book_id, page, paragraph HAVING cnt > 1 LIMIT 10");
$rows = $stmt->fetchAll();
echo "First 10 duplicate groups (book_id, page, paragraph):\n";
foreach ($rows as $row) {
    echo "  book={$row['book_id']}, page={$row['page']}, para={$row['paragraph']} -> count={$row['cnt']}\n";
}

echo "\n=== Total Unique Combinations in MySQL ===\n";
$stmt = $mysql->query("SELECT COUNT(DISTINCT book_id, page, paragraph) as cnt FROM ci_book_meta");
$unique = $stmt->fetch()['cnt'];
echo "Unique (book_id, page, paragraph) combinations: $unique\n";

$stmt = $mysql->query("SELECT COUNT(*) as cnt FROM ci_book_meta");
$total = $stmt->fetch()['cnt'];
echo "Total rows: $total\n";
echo "Duplicates: " . ($total - $unique) . "\n";

echo "\n=== PostgreSQL book_contents rows ===\n";
$stmt = $pg->query("SELECT COUNT(*) as cnt FROM book_contents");
echo "Total: " . $stmt->fetch()['cnt'] . "\n";
