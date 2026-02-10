<?php
/**
 * Categories Migration Script
 * Migrate from MySQL (ci_category) to PostgreSQL (categories)
 */

declare(strict_types=1);

// Database config
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

echo "🚀 Categories Migration Script\n";
echo "===============================\n\n";

try {
    // Connect to MySQL
    echo "📡 Connecting to MySQL...\n";
    $mysql = new PDO(
        "mysql:host={$mysqlConfig['host']};port={$mysqlConfig['port']};dbname={$mysqlConfig['dbname']};charset=utf8mb4",
        $mysqlConfig['user'],
        $mysqlConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "   ✅ MySQL connected\n";

    // Connect to PostgreSQL
    echo "📡 Connecting to PostgreSQL...\n";
    $pg = new PDO(
        "pgsql:host={$pgConfig['host']};port={$pgConfig['port']};dbname={$pgConfig['dbname']}",
        $pgConfig['user'],
        $pgConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "   ✅ PostgreSQL connected\n\n";

    // Clear existing categories
    echo "🗑️  Clearing existing categories...\n";
    $pg->exec("UPDATE products SET category_id = NULL");
    $pg->exec("TRUNCATE TABLE categories RESTART IDENTITY CASCADE");
    echo "   ✅ Cleared\n\n";

    // Get all categories from MySQL
    $categories = $mysql->query("SELECT * FROM ci_category ORDER BY parent ASC, position ASC")->fetchAll();
    $totalCats = count($categories);
    echo "📊 Found $totalCats categories to migrate\n\n";

    if ($totalCats === 0) {
        echo "⚠️  No categories found in source database\n";
        exit(0);
    }

    // Phase 1: Insert all categories without parent_id
    echo "⚙️  Phase 1: Inserting categories...\n";
    
    $stmtInsert = $pg->prepare("
        INSERT INTO categories (old_id, title, slug, icon, sort_order, type, is_active)
        VALUES (:old_id, :title, :slug, :icon, :sort_order, :type, TRUE)
        RETURNING id
    ");

    $oldToNew = []; // Map old_id -> new_id
    $oldParents = []; // Map old_id -> old_parent_id
    $usedSlugs = [];
    $migrated = 0;

    $pg->beginTransaction();

    foreach ($categories as $row) {
        $slug = generateSlug($row['name'] ?? '', $row['id'], $usedSlugs);
        $usedSlugs[$slug] = true;

        $type = mapType($row['type'] ?? 'post');

        $stmtInsert->execute([
            'old_id' => $row['id'],
            'title' => $row['name'] ?? 'Untitled',
            'slug' => $slug,
            'icon' => $row['icon'] ?? null,
            'sort_order' => (int)($row['position'] ?? 0),
            'type' => $type,
        ]);

        $newId = $stmtInsert->fetchColumn();
        $oldToNew[$row['id']] = $newId;
        $oldParents[$row['id']] = (int)($row['parent'] ?? 0);
        $migrated++;
    }

    $pg->commit();
    echo "   ✅ Inserted $migrated categories\n\n";

    // Phase 2: Update parent_id references
    echo "⚙️  Phase 2: Setting parent relationships...\n";
    
    $stmtUpdate = $pg->prepare("UPDATE categories SET parent_id = :parent_id WHERE old_id = :old_id");
    $parentsSet = 0;

    $pg->beginTransaction();

    foreach ($oldParents as $oldId => $oldParentId) {
        if ($oldParentId > 0 && isset($oldToNew[$oldParentId])) {
            $stmtUpdate->execute([
                'parent_id' => $oldToNew[$oldParentId],
                'old_id' => $oldId
            ]);
            $parentsSet++;
        }
    }

    $pg->commit();
    echo "   ✅ Set $parentsSet parent relationships\n\n";

    // Phase 3: Calculate depth and full_path
    echo "⚙️  Phase 3: Calculating paths and depths...\n";
    
    // Get all categories with their parent info
    $allCats = $pg->query("SELECT id, parent_id FROM categories")->fetchAll();
    $catMap = [];
    foreach ($allCats as $cat) {
        $catMap[$cat['id']] = $cat['parent_id'];
    }

    $stmtPath = $pg->prepare("UPDATE categories SET depth = :depth, full_path = :full_path WHERE id = :id");
    
    $pg->beginTransaction();

    foreach ($catMap as $catId => $parentId) {
        $path = [];
        $depth = 0;
        $currentId = $catId;
        
        // Build path from current to root
        while ($currentId !== null) {
            array_unshift($path, $currentId);
            $currentId = $catMap[$currentId] ?? null;
            $depth++;
            
            // Prevent infinite loop
            if ($depth > 20) break;
        }
        
        $fullPath = implode('/', $path);
        $depth = count($path) - 1; // depth is levels from root (root = 0)
        
        $stmtPath->execute([
            'depth' => $depth,
            'full_path' => $fullPath,
            'id' => $catId
        ]);
    }

    $pg->commit();
    echo "   ✅ Paths calculated\n\n";

    // Phase 4: Link products to categories
    echo "⚙️  Phase 4: Linking products to categories...\n";
    
    // Get products with old_category from attributes
    $products = $pg->query("
        SELECT id, attributes->>'old_category' as old_cat 
        FROM products 
        WHERE attributes->>'old_category' IS NOT NULL
    ")->fetchAll();

    $stmtLinkProduct = $pg->prepare("UPDATE products SET category_id = :cat_id WHERE id = :id");
    $linked = 0;

    $pg->beginTransaction();

    foreach ($products as $prod) {
        $oldCatStr = $prod['old_cat'];
        if (!empty($oldCatStr)) {
            // old_category might be comma-separated, take first one
            $oldCatId = (int)explode(',', $oldCatStr)[0];
            if (isset($oldToNew[$oldCatId])) {
                $stmtLinkProduct->execute([
                    'cat_id' => $oldToNew[$oldCatId],
                    'id' => $prod['id']
                ]);
                $linked++;
            }
        }
    }

    $pg->commit();
    echo "   ✅ Linked $linked products to categories\n\n";

    // Summary
    echo "=============================\n";
    echo "✅ Migration completed!\n";
    echo "   Categories: $migrated\n";
    echo "   With parents: $parentsSet\n";
    echo "   Products linked: $linked\n";

    // Verify
    $pgCount = (int)$pg->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    echo "\n📊 Verification: $pgCount categories in PostgreSQL\n";

    // Show hierarchy
    echo "\n📂 Category tree (root level):\n";
    $roots = $pg->query("SELECT id, title, (SELECT COUNT(*) FROM categories c2 WHERE c2.parent_id = c.id) as children FROM categories c WHERE parent_id IS NULL ORDER BY sort_order LIMIT 15")->fetchAll();
    foreach ($roots as $r) {
        $children = $r['children'] > 0 ? " ({$r['children']} children)" : "";
        echo "   - {$r['title']}{$children}\n";
    }

    echo "\n🎉 Done!\n";

} catch (Exception $e) {
    if (isset($pg) && $pg->inTransaction()) {
        $pg->rollBack();
    }
    echo "\n❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Helper functions
function mapType(?string $type): string
{
    $map = [
        'post' => 'book',
        'book' => 'book',
        'audio' => 'audiobook',
        'course' => 'course',
    ];
    return $map[strtolower($type ?? 'post')] ?? 'book';
}

function generateSlug(?string $title, int $id, array $usedSlugs): string
{
    if (empty($title)) {
        return 'category-' . $id;
    }

    $slug = mb_strtolower(trim($title));
    $slug = preg_replace('/\s+/u', '-', $slug);
    $slug = preg_replace('/[^\p{L}\p{N}\-]/u', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    if (mb_strlen($slug) > 200) {
        $slug = mb_substr($slug, 0, 200);
    }
    
    if (empty($slug)) {
        $slug = 'category-' . $id;
    }
    
    $baseSlug = $slug;
    $counter = 1;
    while (isset($usedSlugs[$slug])) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}
