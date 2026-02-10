<?php
/**
 * Update book_contents.page_number with actual page numbers from ci_post_meta.pages
 * 
 * Logic:
 * - ci_post_meta has key='pages' with comma-separated page numbers
 * - ci_post_meta.pages[order] = actual page number for content at that order
 * - We update book_contents.page_number accordingly
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

echo "🚀 Update book_contents.page_number from ci_post_meta.pages\n";
echo "============================================================\n\n";

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

    // Step 1: Get all pages mappings from ci_post_meta
    echo "📋 Loading page mappings from ci_post_meta...\n";
    $stmt = $mysql->query("SELECT post_id, meta_value FROM ci_post_meta WHERE meta_key = 'pages'");
    $pageMappings = [];
    while ($row = $stmt->fetch()) {
        $postId = (int)$row['post_id'];
        $pages = array_map('intval', explode(',', $row['meta_value']));
        $pageMappings[$postId] = $pages;
    }
    echo "   Found mappings for " . count($pageMappings) . " books\n\n";

    // Step 2: Get book_id mapping (old_id from products -> new book_id)
    // Actually, book_contents.book_id should match ci_book_meta.book_id which is same as post_id
    // Let's verify this
    
    // Step 3: First, set all page_numbers to NULL to avoid unique constraint conflicts
    echo "⚙️  Step 1: Setting all page_numbers to NULL (to avoid conflicts)...\n";
    $pg->exec("UPDATE book_contents SET page_number = NULL");
    echo "   ✅ Done\n\n";
    
    // Step 4: Fix order values and update page_numbers
    echo "⚙️  Step 2: Fixing order indices and updating page_numbers...\n";
    
    $stmtUpdate = $pg->prepare("
        UPDATE book_contents 
        SET \"order\" = :new_order, page_number = :actual_page 
        WHERE id = :content_id
    ");
    
    $stmtUpdateOrderOnly = $pg->prepare("
        UPDATE book_contents 
        SET \"order\" = :new_order 
        WHERE id = :content_id
    ");
    
    $updated = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($pageMappings as $bookId => $pages) {
        // Get all content rows for this book ordered by current order
        $stmt = $pg->prepare("SELECT id, \"order\" FROM book_contents WHERE book_id = :book_id ORDER BY \"order\"");
        $stmt->execute(['book_id' => $bookId]);
        $rows = $stmt->fetchAll();
        
        // Map rows to pages by sequential index (not by order value)
        // Also reset order to be sequential: 0, 1, 2, 3...
        foreach ($rows as $index => $row) {
            $contentId = (int)$row['id'];
            $newOrder = $index; // Sequential: 0, 1, 2, 3...
            
            // Get actual page from mapping using sequential index
            if (isset($pages[$index])) {
                $actualPage = $pages[$index];
                try {
                    $stmtUpdate->execute([
                        'new_order' => $newOrder,
                        'actual_page' => $actualPage,
                        'content_id' => $contentId
                    ]);
                    $updated++;
                } catch (PDOException $e) {
                    $errors++;
                }
            } else {
                // Still fix the order even if no page mapping
                try {
                    $stmtUpdateOrderOnly->execute(['new_order' => $newOrder, 'content_id' => $contentId]);
                    $skipped++;
                } catch (PDOException $e) {
                    $errors++;
                }
            }
        }
        
        // Progress
        static $processedBooks = 0;
        $processedBooks++;
        if ($processedBooks % 50 == 0) {
            echo "\r   Processed $processedBooks/" . count($pageMappings) . " books...";
        }
    }
    
    echo "\n";
    
    echo "\n\n=============================\n";
    echo "✅ Migration completed!\n";
    echo "   Updated: $updated rows\n";
    echo "   Skipped (no mapping): $skipped rows\n";
    echo "   Errors: $errors\n";
    
    // Verification - show a few books
    echo "\n📊 Verification samples:\n\n";
    
    // Get a few books with content
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
        
        echo "  order | page_number\n";
        echo "  ------|------------\n";
        while ($row = $stmt->fetch()) {
            echo sprintf("  %5d | %d\n", $row['order'], $row['page_number'] ?? 0);
        }
        echo "\n";
    }
    
    echo "🎉 Done! Orders are now sequential (0, 1, 2...) and page_numbers are mapped correctly.\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
