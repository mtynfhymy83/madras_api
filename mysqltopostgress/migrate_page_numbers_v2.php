<?php
/**
 * Update book_contents.page_number with actual page numbers
 * 
 * Logic:
 * - ci_book_meta.page is sequential (1, 2, 3, ...)
 * - ci_post_meta.pages contains actual book page numbers (3, 8, 10, 13, ...)
 * - Mapping: content with page=1 gets page_number=pages[0], page=2 gets pages[1], etc.
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

echo "🚀 Update book_contents.page_number with actual book page numbers\n";
echo "==================================================================\n\n";

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

    // Step 1: Get pages mapping from ci_post_meta
    echo "📋 Loading actual page numbers from ci_post_meta...\n";
    $stmt = $mysql->query("SELECT post_id, meta_value FROM ci_post_meta WHERE meta_key = 'pages'");
    $pageMappings = [];
    while ($row = $stmt->fetch()) {
        $bookId = (int)$row['post_id'];
        $pages = array_map('intval', explode(',', $row['meta_value']));
        $pageMappings[$bookId] = $pages;
    }
    echo "   Found mappings for " . count($pageMappings) . " books\n\n";

    // Step 2: For each book, get content with their sequential page from ci_book_meta
    echo "⚙️  Updating page numbers...\n";
    
    $stmtUpdate = $pg->prepare("
        UPDATE book_contents 
        SET page_number = :actual_page 
        WHERE book_id = :book_id AND id = (
            SELECT id FROM book_contents 
            WHERE book_id = :book_id 
            ORDER BY \"order\" 
            LIMIT 1 OFFSET :offset
        )
    ");
    
    $updated = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($pageMappings as $bookId => $pages) {
        // Get all content from MySQL ci_book_meta with their page numbers
        $stmt = $mysql->prepare("
            SELECT `order`, `page` 
            FROM ci_book_meta 
            WHERE book_id = :book_id 
            ORDER BY `order`
        ");
        $stmt->execute(['book_id' => $bookId]);
        $contents = $stmt->fetchAll();
        
        if (empty($contents)) {
            continue;
        }
        
        foreach ($contents as $content) {
            $order = (int)$content['order'];
            $sequentialPage = (int)$content['page']; // 1, 2, 3, 4...
            
            // Map sequential page to actual book page number
            // page=1 → pages[0], page=2 → pages[1], etc.
            $pageIndex = $sequentialPage - 1;
            
            if (isset($pages[$pageIndex])) {
                $actualPageNumber = $pages[$pageIndex];
                
                try {
                    // Update by book_id and order offset
                    $stmtDirectUpdate = $pg->prepare("
                        UPDATE book_contents 
                        SET page_number = :actual_page 
                        WHERE book_id = :book_id AND \"order\" = :order
                    ");
                    $stmtDirectUpdate->execute([
                        'actual_page' => $actualPageNumber,
                        'book_id' => $bookId,
                        'order' => $order
                    ]);
                    $updated++;
                } catch (PDOException $e) {
                    $errors++;
                }
            } else {
                $skipped++;
            }
        }
        
        // Progress
        static $processedBooks = 0;
        $processedBooks++;
        if ($processedBooks % 50 == 0) {
            echo "\r   Processed $processedBooks/" . count($pageMappings) . " books...";
        }
    }
    
    echo "\n\n=============================\n";
    echo "✅ Migration completed!\n";
    echo "   Updated: $updated rows\n";
    echo "   Skipped (no mapping): $skipped rows\n";
    echo "   Errors: $errors\n";
    
    // Verification
    echo "\n📊 Verification samples:\n\n";
    
    $booksToCheck = $pg->query("
        SELECT DISTINCT book_id 
        FROM book_contents 
        WHERE page_number IS NOT NULL 
        ORDER BY book_id 
        LIMIT 3
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($booksToCheck as $bookId) {
        echo "Book ID: $bookId\n";
        $stmt = $pg->prepare("
            SELECT id, \"order\", page_number 
            FROM book_contents 
            WHERE book_id = :book_id 
            ORDER BY \"order\" 
            LIMIT 10
        ");
        $stmt->execute(['book_id' => $bookId]);
        
        echo "  order | page_number (actual)\n";
        echo "  ------|--------------------\n";
        while ($row = $stmt->fetch()) {
            echo sprintf("  %5d | %s\n", $row['order'], $row['page_number'] ?? 'NULL');
        }
        echo "\n";
    }
    
    echo "🎉 Done! Page numbers now reflect actual book page numbers.\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
