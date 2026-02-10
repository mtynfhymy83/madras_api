<?php
/**
 * Link products to categories using MySQL source data
 */

$mysqlConfig = ['host' => 'localhost', 'dbname' => 'madras', 'user' => 'root', 'pass' => 'pass'];
$pgConfig = ['host' => 'localhost', 'dbname' => 'madras', 'user' => 'myuser', 'pass' => 'mypass'];

echo "🔗 Linking Products to Categories\n";
echo "==================================\n\n";

try {
    $mysql = new PDO("mysql:host={$mysqlConfig['host']};dbname={$mysqlConfig['dbname']};charset=utf8mb4", 
        $mysqlConfig['user'], $mysqlConfig['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pg = new PDO("pgsql:host={$pgConfig['host']};dbname={$pgConfig['dbname']}", 
        $pgConfig['user'], $pgConfig['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Build category map: old_id -> new_id
    echo "📂 Building category map...\n";
    $catMap = [];
    $r = $pg->query("SELECT id, old_id FROM categories WHERE old_id IS NOT NULL");
    while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
        $catMap[$row['old_id']] = $row['id'];
    }
    echo "   Found " . count($catMap) . " categories\n\n";

    // Build product map: old_id -> new_id
    echo "📦 Building product map...\n";
    $prodMap = [];
    $r = $pg->query("SELECT id, old_id FROM products WHERE old_id IS NOT NULL");
    while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
        $prodMap[$row['old_id']] = $row['id'];
    }
    echo "   Found " . count($prodMap) . " products\n\n";

    // Get posts with category from MySQL
    echo "🔗 Linking products...\n";
    $posts = $mysql->query("SELECT id, category FROM ci_posts WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtUpdate = $pg->prepare("UPDATE products SET category_id = :cat_id WHERE id = :prod_id");
    $linked = 0;
    $notFound = 0;

    $pg->beginTransaction();

    foreach ($posts as $post) {
        $oldPostId = $post['id'];
        $oldCatId = (int)$post['category'];

        if (isset($prodMap[$oldPostId]) && isset($catMap[$oldCatId])) {
            $stmtUpdate->execute([
                'cat_id' => $catMap[$oldCatId],
                'prod_id' => $prodMap[$oldPostId]
            ]);
            $linked++;
        } else {
            $notFound++;
        }
    }

    $pg->commit();

    echo "\n=============================\n";
    echo "✅ Done!\n";
    echo "   Linked: $linked products\n";
    echo "   Not found: $notFound\n";

    // Verify
    $withCat = (int)$pg->query("SELECT COUNT(*) FROM products WHERE category_id IS NOT NULL")->fetchColumn();
    echo "\n📊 Products with category: $withCat\n";

} catch (Exception $e) {
    if (isset($pg) && $pg->inTransaction()) $pg->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
}
