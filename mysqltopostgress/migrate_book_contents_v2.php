<?php
/**
 * Migration: ci_book_meta (MySQL) -> book_contents (PostgreSQL)
 * Version 2 - Clean implementation based on actual database structures
 * 
 * Usage:
 *   php migrate_book_contents_v2.php              # Interactive mode
 *   php migrate_book_contents_v2.php --force      # Non-interactive
 *   php migrate_book_contents_v2.php --truncate   # Clear existing data first
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ===================== Configuration =====================

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

// ===================== Parse Arguments =====================

$force = in_array('--force', $argv ?? []);
$truncate = in_array('--truncate', $argv ?? []);

// ===================== Connect =====================

echo "\n=== Book Contents Migration ===\n\n";

// MySQL
try {
    $mysqlDsn = "mysql:host={$mysqlConfig['host']};port={$mysqlConfig['port']};dbname={$mysqlConfig['dbname']};charset=utf8mb4";
    $mysql = new PDO($mysqlDsn, $mysqlConfig['user'], $mysqlConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[OK] MySQL connected\n";
} catch (PDOException $e) {
    die("[FAIL] MySQL: " . $e->getMessage() . "\n");
}

// PostgreSQL
try {
    $pgDsn = "pgsql:host={$pgConfig['host']};port={$pgConfig['port']};dbname={$pgConfig['dbname']}";
    $pg = new PDO($pgDsn, $pgConfig['user'], $pgConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[OK] PostgreSQL connected\n\n";
} catch (PDOException $e) {
    die("[FAIL] PostgreSQL: " . $e->getMessage() . "\n");
}

// ===================== Pre-checks =====================

// Check source count
$stmt = $mysql->query("SELECT COUNT(*) as cnt FROM ci_book_meta");
$sourceCount = (int)$stmt->fetch()['cnt'];
echo "Source rows (MySQL):      $sourceCount\n";

// Check destination count
$stmt = $pg->query("SELECT COUNT(*) as cnt FROM book_contents");
$destCount = (int)$stmt->fetch()['cnt'];
echo "Destination rows (PgSQL): $destCount\n\n";

// Truncate if requested
if ($truncate) {
    echo "[INFO] Truncating book_contents table...\n";
    $pg->exec("TRUNCATE TABLE book_contents RESTART IDENTITY CASCADE");
    $destCount = 0;
    echo "[OK] Table truncated\n\n";
}

if ($destCount > 0 && !$truncate) {
    echo "[WARNING] Destination table is not empty. Use --truncate to clear first.\n";
    echo "          Existing rows will be skipped (ON CONFLICT DO NOTHING)\n\n";
}

// Confirmation
if (!$force) {
    echo "Ready to migrate $sourceCount rows. Continue? [y/N]: ";
    $answer = trim(fgets(STDIN));
    if (strtolower($answer) !== 'y') {
        die("Aborted.\n");
    }
}

// ===================== Prepare Statement =====================

// PostgreSQL insert with explicit column names and parameter binding
$insertSql = "
    INSERT INTO book_contents (
        book_id, page_number, paragraph_number, \"order\",
        text, description, sound_path, image_paths, video_path,
        is_index, index_title, index_level,
        created_at, updated_at
    ) VALUES (
        :book_id, :page_number, :paragraph_number, :order_num,
        :text, :description, :sound_path, :image_paths, :video_path,
        :is_index, :index_title, :index_level,
        :created_at, :updated_at
    )
    ON CONFLICT DO NOTHING
";

$insertStmt = $pg->prepare($insertSql);

// ===================== Migration Loop =====================

$batchSize = 500;
$offset = 0;
$migrated = 0;
$skipped = 0;
$errors = 0;
$startTime = microtime(true);

echo "\n[START] Migrating...\n\n";

while (true) {
    // Fetch batch from MySQL
    $sql = "SELECT * FROM ci_book_meta ORDER BY id LIMIT $batchSize OFFSET $offset";
    $stmt = $mysql->query($sql);
    $rows = $stmt->fetchAll();
    
    if (empty($rows)) {
        break;
    }
    
    foreach ($rows as $row) {
        // Skip if book_id is null
        if (empty($row['book_id'])) {
            $skipped++;
            continue;
        }
        
        try {
            // Prepare values with proper type handling
            $bookId = (int)$row['book_id'];
            $pageNumber = (int)($row['page'] ?? 1);
            $paragraphNumber = (int)($row['paragraph'] ?? 1);
            $orderNum = (int)($row['order'] ?? 0);
            
            // Text fields - ensure they are strings or null
            $text = !empty($row['text']) ? $row['text'] : null;
            $description = !empty($row['description']) ? $row['description'] : null;
            
            // Media paths - empty strings become null
            $soundPath = !empty($row['sound']) ? $row['sound'] : null;
            $videoPath = !empty($row['video']) ? $row['video'] : null;
            
            // Image - convert to JSON array if not empty
            $imagePaths = null;
            if (!empty($row['image']) && trim($row['image']) !== '') {
                // If it's already JSON, keep it; otherwise wrap in array
                $decoded = json_decode($row['image'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $imagePaths = $row['image'];
                } else {
                    $imagePaths = json_encode([$row['image']]);
                }
            }
            
            // Index handling
            $indexValue = max((int)($row['index'] ?? 0), (int)($row['fehrest'] ?? 0));
            $isIndex = $indexValue > 0;
            
            // Extract index title from first line of text
            $indexTitle = null;
            if ($isIndex && $text) {
                $lines = explode("\n", $text);
                $firstLine = trim($lines[0] ?? '');
                // Remove markdown headers (#, ##, etc.)
                $firstLine = preg_replace('/^#+\s*/', '', $firstLine);
                $indexTitle = mb_substr($firstLine, 0, 250);
            }
            
            // Calculate index level (1-3)
            $indexLevel = 0;
            if ($isIndex && $indexValue > 0) {
                if ($indexValue < 100) {
                    $indexLevel = 1;
                } elseif ($indexValue < 1000) {
                    $indexLevel = 2;
                } else {
                    $indexLevel = 3;
                }
            }
            
            // Timestamps
            $now = date('Y-m-d H:i:s');
            
            // Execute insert with named parameters
            $insertStmt->execute([
                ':book_id' => $bookId,
                ':page_number' => $pageNumber,
                ':paragraph_number' => $paragraphNumber,
                ':order_num' => $orderNum,
                ':text' => $text,
                ':description' => $description,
                ':sound_path' => $soundPath,
                ':image_paths' => $imagePaths,
                ':video_path' => $videoPath,
                ':is_index' => $isIndex ? 't' : 'f',  // PostgreSQL boolean
                ':index_title' => $indexTitle,
                ':index_level' => $indexLevel,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            
            $migrated++;
            
        } catch (PDOException $e) {
            $errors++;
            if ($errors <= 5) {
                echo "[ERROR] Row ID {$row['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    $offset += $batchSize;
    $progress = min(100, round(($offset / $sourceCount) * 100));
    $elapsed = round(microtime(true) - $startTime, 1);
    
    echo "\r  Progress: {$progress}% | Migrated: {$migrated} | Skipped: {$skipped} | Errors: {$errors} | Time: {$elapsed}s";
}

// ===================== Summary =====================

$totalTime = round(microtime(true) - $startTime, 2);

echo "\n\n=== Migration Complete ===\n\n";
echo "Source rows:     $sourceCount\n";
echo "Migrated:        $migrated\n";
echo "Skipped:         $skipped\n";
echo "Errors:          $errors\n";
echo "Time:            {$totalTime}s\n";

// Verify
$stmt = $pg->query("SELECT COUNT(*) as cnt FROM book_contents");
$finalCount = (int)$stmt->fetch()['cnt'];
echo "\nPostgreSQL now has: $finalCount rows\n";

if ($errors > 0) {
    echo "\n[WARNING] There were $errors errors. Check logs above.\n";
}

echo "\n[DONE]\n";
