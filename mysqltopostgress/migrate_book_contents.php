<?php
/**
 * Migration script: ci_book_meta (MySQL) -> book_contents (PostgreSQL)
 * 
 * Usage:
 *   php migrate_book_contents.php [--dry-run] [--book-id=123]
 * 
 * Options:
 *   --dry-run     Only show what would be migrated, don't actually migrate
 *   --book-id=123 Only migrate contents for a specific book
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// MySQL (source) configuration
$mysqlConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'madras',
    'user' => 'root',
    'pass' => 'pass'
];

// PostgreSQL (destination) configuration
$pgConfig = [
    'host' => 'localhost',
    'port' => 5432,
    'dbname' => 'madras',
    'user' => 'myuser',
    'pass' => 'mypass'
];

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv ?? []);
$bookId = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--book-id=')) {
        $bookId = (int) substr($arg, 10);
    }
}

echo "===========================================\n";
echo "📚 Book Contents Migration\n";
echo "   ci_book_meta (MySQL) -> book_contents (PostgreSQL)\n";
echo "===========================================\n\n";

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No data will be written\n\n";
}

// Connect to MySQL
try {
    $mysqlDsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
        $mysqlConfig['host'],
        $mysqlConfig['port'],
        $mysqlConfig['dbname']
    );
    $mysql = new PDO($mysqlDsn, $mysqlConfig['user'], $mysqlConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✅ Connected to MySQL: {$mysqlConfig['dbname']}\n";
} catch (PDOException $e) {
    die("❌ MySQL connection failed: " . $e->getMessage() . "\n");
}

// Connect to PostgreSQL
try {
    $pgDsn = sprintf(
        "pgsql:host=%s;port=%d;dbname=%s",
        $pgConfig['host'],
        $pgConfig['port'],
        $pgConfig['dbname']
    );
    $pg = new PDO($pgDsn, $pgConfig['user'], $pgConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✅ Connected to PostgreSQL: {$pgConfig['dbname']}\n\n";
} catch (PDOException $e) {
    die("❌ PostgreSQL connection failed: " . $e->getMessage() . "\n");
}

// First, let's see the structure of ci_book_meta
echo "📋 Analyzing ci_book_meta structure...\n";

$stmt = $mysql->query("DESCRIBE ci_book_meta");
$columns = $stmt->fetchAll();

echo "\n   Columns in ci_book_meta:\n";
$columnNames = [];
foreach ($columns as $col) {
    echo "   - {$col['Field']} ({$col['Type']})\n";
    $columnNames[] = $col['Field'];
}

// Get total count
$whereClause = $bookId ? "WHERE book_id = $bookId" : "";
$stmt = $mysql->query("SELECT COUNT(*) as cnt FROM ci_book_meta $whereClause");
$totalRows = (int) $stmt->fetch()['cnt'];

echo "\n   Total rows to migrate: $totalRows\n\n";

if ($totalRows === 0) {
    echo "⚠️  No rows to migrate.\n";
    exit(0);
}

// Get sample row
echo "📝 Sample row from ci_book_meta:\n";
$stmt = $mysql->query("SELECT * FROM ci_book_meta $whereClause LIMIT 1");
$sample = $stmt->fetch();
foreach ($sample as $key => $value) {
    $displayValue = $value;
    if (is_string($value) && strlen($value) > 100) {
        $displayValue = substr($value, 0, 100) . '...';
    }
    echo "   - $key: $displayValue\n";
}

// Define column mapping
// Adjust this based on actual ci_book_meta structure
echo "\n🔄 Column Mapping:\n";

$columnMapping = [
    // ci_book_meta column => book_contents column
    'id' => 'old_id',  // We'll store old ID for reference
    'book_id' => 'book_id',
    'page' => 'page_number',
    'paragraph' => 'paragraph_number',
    'text' => 'text',
    'description' => 'description',
    'sound' => 'sound_path',
    'image' => 'image_paths',  // Will be converted to JSON array
    'video' => 'video_path',
    'is_index' => 'is_index',
    'index_title' => 'index_title',
    'index_level' => 'index_level',
    'order' => 'order',
];

// Check which columns actually exist
$actualMapping = [];
foreach ($columnMapping as $mysqlCol => $pgCol) {
    if (in_array($mysqlCol, $columnNames)) {
        $actualMapping[$mysqlCol] = $pgCol;
        echo "   ✅ $mysqlCol -> $pgCol\n";
    } else {
        echo "   ⚠️  $mysqlCol (not found in source)\n";
    }
}

// Alternative column names to check
$alternativeColumns = [
    'page_number' => 'page_number',
    'page_no' => 'page_number',
    'paragraph_number' => 'paragraph_number',
    'para' => 'paragraph_number',
    'content' => 'text',
    'body' => 'text',
    'audio' => 'sound_path',
    'sound_path' => 'sound_path',
    'audio_path' => 'sound_path',
    'images' => 'image_paths',
    'image_path' => 'image_paths',
    'sort_order' => 'order',
    'sort' => 'order',
    'title' => 'index_title',
];

foreach ($alternativeColumns as $mysqlCol => $pgCol) {
    if (in_array($mysqlCol, $columnNames) && !isset($actualMapping[$mysqlCol])) {
        $actualMapping[$mysqlCol] = $pgCol;
        echo "   ✅ $mysqlCol -> $pgCol (alternative)\n";
    }
}

if ($dryRun) {
    echo "\n🔍 DRY RUN - Would migrate $totalRows rows\n";
    echo "   Run without --dry-run to perform actual migration.\n";
    exit(0);
}

// Ask for confirmation (skip in non-interactive mode)
if (!in_array('--force', $argv ?? []) && posix_isatty(STDIN)) {
    echo "\n⚠️  Ready to migrate $totalRows rows.\n";
    echo "   Press Enter to continue or Ctrl+C to cancel...\n";
    fgets(STDIN);
} else {
    echo "\n⚠️  Migrating $totalRows rows (--force or non-interactive mode)...\n";
}

// Start migration
echo "\n🚀 Starting migration...\n\n";

$batchSize = 100;
$offset = 0;
$migrated = 0;
$errors = 0;
$skipped = 0;

// Prepare PostgreSQL insert statement with ? placeholders
$insertSql = 'INSERT INTO book_contents 
    (book_id, page_number, paragraph_number, text, description, 
     sound_path, image_paths, video_path, is_index, index_title, 
     index_level, "order", created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT DO NOTHING';

// Prepare statement once
$insertStmt = $pg->prepare($insertSql);

// Process without global transaction (each row is independent)
while ($offset < $totalRows) {
    $sql = "SELECT * FROM ci_book_meta $whereClause ORDER BY book_id, id LIMIT $batchSize OFFSET $offset";
    $stmt = $mysql->query($sql);
    $rows = $stmt->fetchAll();
    
    if (empty($rows)) {
        break;
    }
    
    foreach ($rows as $row) {
        try {
            // Map columns
            $bookIdVal = $row['book_id'] ?? $row['post_id'] ?? null;
            
            if (!$bookIdVal) {
                $skipped++;
                continue;
            }
            
            // Get page number
            $pageNumber = (int) ($row['page'] ?? $row['page_number'] ?? $row['page_no'] ?? 1);
            
            // Get paragraph number
            $paragraphNumber = (int) ($row['paragraph'] ?? $row['paragraph_number'] ?? $row['para'] ?? 1);
            
            // Get text content
            $text = $row['text'] ?? $row['content'] ?? $row['body'] ?? null;
            
            // Get description
            $description = $row['description'] ?? $row['desc'] ?? null;
            
            // Get audio path
            $soundPath = $row['sound'] ?? $row['audio'] ?? $row['sound_path'] ?? $row['audio_path'] ?? null;
            if ($soundPath === '') $soundPath = null;
            
            // Get image paths (convert to JSON array)
            $imagePaths = $row['image'] ?? $row['images'] ?? $row['image_path'] ?? null;
            if ($imagePaths && !empty(trim($imagePaths)) && !str_starts_with($imagePaths, '[')) {
                // Single image, convert to array
                $imagePaths = json_encode([$imagePaths]);
            } elseif (empty($imagePaths) || trim($imagePaths) === '') {
                $imagePaths = null;
            }
            
            // Get video path
            $videoPath = $row['video'] ?? $row['video_path'] ?? null;
            if ($videoPath === '') $videoPath = null;
            
            // Get index info
            // In old database: 'index' and 'fehrest' columns indicate index items
            // If 'index' or 'fehrest' has a value > 0, it's an index item
            $indexValue = (int) ($row['index'] ?? $row['fehrest'] ?? 0);
            $isIndex = $indexValue > 0;
            
            // Use first line of text as index title if it's an index item
            $indexTitle = null;
            if ($isIndex && $text) {
                // Extract first line or first 100 chars as title
                $firstLine = strtok($text, "\n");
                // Remove markdown # symbols
                $indexTitle = trim(preg_replace('/^#+\s*/', '', $firstLine));
                if (strlen($indexTitle) > 200) {
                    $indexTitle = mb_substr($indexTitle, 0, 200) . '...';
                }
            }
            
            // Index level based on index value (higher = deeper level)
            $indexLevel = $isIndex ? min(3, max(1, (int) log10($indexValue + 1))) : 0;
            
            // Get order
            $order = (int) ($row['order'] ?? $row['sort_order'] ?? $row['sort'] ?? $row['id'] ?? 0);
            
            // Timestamps
            $createdAt = $row['created_at'] ?? $row['created'] ?? $row['date'] ?? date('Y-m-d H:i:s');
            $updatedAt = $row['updated_at'] ?? $row['updated'] ?? $row['modified'] ?? date('Y-m-d H:i:s');
            
            // Insert into PostgreSQL
            $values = [
                (int) $bookIdVal,
                $pageNumber,
                $paragraphNumber,
                $text,
                $description,
                $soundPath,
                $imagePaths,
                $videoPath,
                $isIndex,
                $indexTitle,
                $indexLevel,
                $order,
                $createdAt,
                $updatedAt
            ];
            
            $insertStmt->execute($values);
            
            $migrated++;
            
        } catch (PDOException $e) {
            $errors++;
            if ($errors <= 5) {
                echo "\n   ⚠️  Error row ID {$row['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    $offset += $batchSize;
    $progress = min(100, round(($offset / $totalRows) * 100));
    echo "\r   Progress: $progress% ($migrated migrated, $errors errors, $skipped skipped)";
}

echo "\n\n";
echo "===========================================\n";
echo "✅ Migration Complete!\n";
echo "===========================================\n";
echo "   Total rows:    $totalRows\n";
echo "   Migrated:      $migrated\n";
echo "   Errors:        $errors\n";
echo "   Skipped:       $skipped\n";
echo "===========================================\n";

// Verify migration
$stmt = $pg->query("SELECT COUNT(*) as cnt FROM book_contents");
$pgCount = (int) $stmt->fetch()['cnt'];
echo "\n📊 PostgreSQL book_contents now has: $pgCount rows\n";
