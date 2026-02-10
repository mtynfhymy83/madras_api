<?php
/**
 * Check database structures before migration
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$mysqlConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'madras',
    'user' => 'root',
    'pass' => 'pass'
];

$pgConfig = [
    'host' => 'localhost',
    'port' => 5432,
    'dbname' => 'madras',
    'user' => 'myuser',
    'pass' => 'mypass'
];

echo "=".str_repeat("=", 60)."\n";
echo "DATABASE STRUCTURE COMPARISON\n";
echo "=".str_repeat("=", 60)."\n\n";

// Connect to MySQL
try {
    $mysqlDsn = "mysql:host={$mysqlConfig['host']};port={$mysqlConfig['port']};dbname={$mysqlConfig['dbname']};charset=utf8mb4";
    $mysql = new PDO($mysqlDsn, $mysqlConfig['user'], $mysqlConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[OK] Connected to MySQL\n";
} catch (PDOException $e) {
    die("[ERROR] MySQL: " . $e->getMessage() . "\n");
}

// Connect to PostgreSQL
try {
    $pgDsn = "pgsql:host={$pgConfig['host']};port={$pgConfig['port']};dbname={$pgConfig['dbname']}";
    $pg = new PDO($pgDsn, $pgConfig['user'], $pgConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[OK] Connected to PostgreSQL\n\n";
} catch (PDOException $e) {
    die("[ERROR] PostgreSQL: " . $e->getMessage() . "\n");
}

// ===================== MySQL Structure =====================
echo "-".str_repeat("-", 60)."\n";
echo "MySQL: ci_book_meta\n";
echo "-".str_repeat("-", 60)."\n\n";

$stmt = $mysql->query("DESCRIBE ci_book_meta");
$mysqlCols = $stmt->fetchAll();

echo sprintf("%-20s | %-15s | %-8s | %-20s\n", "Column", "Type", "Null", "Default");
echo str_repeat("-", 70) . "\n";
foreach ($mysqlCols as $col) {
    echo sprintf("%-20s | %-15s | %-8s | %-20s\n", 
        $col['Field'], 
        $col['Type'], 
        $col['Null'], 
        $col['Default'] ?? 'NULL'
    );
}

// Total rows
$stmt = $mysql->query("SELECT COUNT(*) as cnt FROM ci_book_meta");
$cnt = $stmt->fetch()['cnt'];
echo "\nTotal rows: $cnt\n";

// Sample data
echo "\n--- Sample Data (3 rows) ---\n\n";
$stmt = $mysql->query("SELECT * FROM ci_book_meta ORDER BY id LIMIT 3");
$rows = $stmt->fetchAll();
foreach ($rows as $i => $row) {
    echo "Row " . ($i + 1) . ":\n";
    foreach ($row as $key => $val) {
        if ($val === null) {
            $display = "NULL";
        } elseif (is_string($val) && strlen($val) > 60) {
            $display = mb_substr($val, 0, 60) . "...";
        } else {
            $display = $val;
        }
        echo "  $key = $display\n";
    }
    echo "\n";
}

// ===================== PostgreSQL Structure =====================
echo "\n" . "-".str_repeat("-", 60)."\n";
echo "PostgreSQL: book_contents\n";
echo "-".str_repeat("-", 60)."\n\n";

$stmt = $pg->query("
    SELECT column_name, data_type, is_nullable, column_default 
    FROM information_schema.columns 
    WHERE table_name = 'book_contents' 
    ORDER BY ordinal_position
");
$pgCols = $stmt->fetchAll();

if (empty($pgCols)) {
    echo "[WARNING] Table 'book_contents' does not exist!\n";
} else {
    echo sprintf("%-20s | %-15s | %-8s | %-30s\n", "Column", "Type", "Null", "Default");
    echo str_repeat("-", 80) . "\n";
    foreach ($pgCols as $col) {
        $default = $col['column_default'] ?? 'NULL';
        if (strlen($default) > 25) {
            $default = substr($default, 0, 25) . "...";
        }
        echo sprintf("%-20s | %-15s | %-8s | %-30s\n", 
            $col['column_name'], 
            $col['data_type'], 
            $col['is_nullable'], 
            $default
        );
    }
    
    // Total rows
    $stmt = $pg->query("SELECT COUNT(*) as cnt FROM book_contents");
    $cnt = $stmt->fetch()['cnt'];
    echo "\nTotal rows: $cnt\n";
}

// ===================== Mapping Suggestion =====================
echo "\n" . "=".str_repeat("=", 60)."\n";
echo "SUGGESTED COLUMN MAPPING\n";
echo "=".str_repeat("=", 60)."\n\n";

$mapping = [
    'book_id' => 'book_id',
    'page' => 'page_number',
    'paragraph' => 'paragraph_number',
    'order' => 'order',
    'text' => 'text',
    'description' => 'description',
    'sound' => 'sound_path',
    'image' => 'image_paths (as JSON array)',
    'video' => 'video_path',
    'index' => 'is_index (bool: value > 0)',
    'fehrest' => '(combined with index)',
];

echo sprintf("%-15s --> %-30s\n", "MySQL", "PostgreSQL");
echo str_repeat("-", 50) . "\n";
foreach ($mapping as $from => $to) {
    echo sprintf("%-15s --> %-30s\n", $from, $to);
}

echo "\n[INFO] Note: index_title will be extracted from first line of 'text'\n";
echo "[INFO] Note: index_level will be calculated from 'index' or 'fehrest' value\n";
