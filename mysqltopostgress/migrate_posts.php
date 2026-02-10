<?php
/**
 * Posts/Books Migration Script
 * Migrate from MySQL (ci_posts) to PostgreSQL (products)
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

$chunkSize = 500;

echo "🚀 Posts/Products Migration Script\n";
echo "===================================\n\n";

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

    // Clear existing products
    echo "🗑️  Clearing existing products...\n";
    $pg->exec("TRUNCATE TABLE product_contents, product_contributors, order_items, user_library, reading_progress, reviews, bookmarks, highlights, products RESTART IDENTITY CASCADE");
    echo "   ✅ Cleared\n\n";

    // Count total posts
    $totalPosts = (int)$mysql->query("SELECT COUNT(*) FROM ci_posts")->fetchColumn();
    echo "📊 Found $totalPosts posts to migrate\n\n";

    if ($totalPosts === 0) {
        echo "⚠️  No posts found in source database\n";
        exit(0);
    }

    // Prepare PostgreSQL statement
    $stmtProduct = $pg->prepare("
        INSERT INTO products (
            old_id, type, title, slug, status, price, price_with_discount,
            cover_image, description, attributes, view_count, sale_count,
            created_at, updated_at
        ) VALUES (
            :old_id, :type, :title, :slug, :status, :price, :price_with_discount,
            :cover_image, :description, :attributes, :view_count, :sale_count,
            :created_at, :updated_at
        )
        ON CONFLICT (old_id, type) DO UPDATE SET
            title = EXCLUDED.title,
            slug = EXCLUDED.slug,
            status = EXCLUDED.status,
            price = EXCLUDED.price,
            cover_image = EXCLUDED.cover_image,
            description = EXCLUDED.description,
            attributes = EXCLUDED.attributes,
            updated_at = NOW()
        RETURNING id
    ");

    $offset = 0;
    $migrated = 0;
    $skipped = 0;
    $errors = [];
    $usedSlugs = [];

    echo "⚙️  Migrating posts...\n";

    while ($offset < $totalPosts) {
        $rows = $mysql->query("SELECT * FROM ci_posts ORDER BY id LIMIT $chunkSize OFFSET $offset")->fetchAll();

        foreach ($rows as $row) {
            try {
                $pg->beginTransaction();

                // Map type
                $type = mapType($row['type'] ?? 'post');
                
                // Map status: published=1 -> status=1, published=0 -> status=0
                $status = (int)($row['published'] ?? 0);
                
                // Generate slug
                $slug = generateSlug($row['title'] ?? '', $row['id'], $usedSlugs);
                $usedSlugs[$slug] = true;
                
                // Convert date
                $createdAt = $row['date'] ?? date('Y-m-d H:i:s');
                if (is_numeric($createdAt)) {
                    $createdAt = date('Y-m-d H:i:s', (int)$createdAt);
                }

                // Build attributes JSONB
                $attributes = [
                    'pages' => (int)($row['pages'] ?? 0),
                    'file_size' => (int)($row['size'] ?? 0),
                    'part_count' => (int)($row['part_count'] ?? 0),
                    'has_description' => (bool)($row['has_description'] ?? 0),
                    'has_sound' => (bool)($row['has_sound'] ?? 0),
                    'has_video' => (bool)($row['has_video'] ?? 0),
                    'has_image' => (bool)($row['has_image'] ?? 0),
                    'has_test' => (bool)($row['has_test'] ?? 0),
                    'has_tashrihi' => (bool)($row['has_tashrihi'] ?? 0),
                    'has_download' => (bool)($row['has_download'] ?? 0),
                    'is_special' => (bool)($row['special'] ?? 0),
                    'accept_comment' => (bool)($row['accept_cm'] ?? 1),
                    'has_membership' => (bool)($row['has_membership'] ?? 0),
                    'position' => (int)($row['position'] ?? 0),
                ];

                // Add optional fields
                if (!empty($row['tags'])) {
                    $attributes['tags'] = $row['tags'];
                }
                if (!empty($row['meta_keywords'])) {
                    $attributes['meta_keywords'] = $row['meta_keywords'];
                }
                if (!empty($row['meta_description'])) {
                    $attributes['meta_description'] = $row['meta_description'];
                }
                if (!empty($row['icon'])) {
                    $attributes['icon'] = $row['icon'];
                }
                if (!empty($row['category'])) {
                    $attributes['old_category'] = $row['category'];
                }
                if (!empty($row['author'])) {
                    $attributes['old_author_id'] = (int)$row['author'];
                }

                // Description: use excerpt or content
                $description = $row['excerpt'] ?? $row['content'] ?? null;

                // Insert product
                $stmtProduct->execute([
                    'old_id' => $row['id'],
                    'type' => $type,
                    'title' => $row['title'] ?? 'Untitled',
                    'slug' => $slug,
                    'status' => $status,
                    'price' => (int)($row['price'] ?? 0),
                    'price_with_discount' => (int)($row['price'] ?? 0),
                    'cover_image' => $row['thumb'] ?? null,
                    'description' => $description,
                    'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
                    'view_count' => 0,
                    'sale_count' => (int)($row['has_bought'] ?? 0),
                    'created_at' => $createdAt,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $pg->commit();
                $migrated++;

            } catch (PDOException $e) {
                $pg->rollBack();
                $skipped++;
                if (count($errors) < 50) {
                    $errors[] = "ID {$row['id']}: " . substr($e->getMessage(), 0, 120);
                }
            }
        }

        $offset += $chunkSize;
        $percent = min(100, round(($offset / $totalPosts) * 100));
        echo "\r   Progress: $migrated migrated, $skipped skipped ($percent%)      ";
    }

    echo "\n\n";
    echo "=============================\n";
    echo "✅ Migration completed!\n";
    echo "   Migrated: $migrated products\n";
    
    if ($skipped > 0) {
        echo "   Skipped: $skipped\n";
    }

    if (!empty($errors)) {
        echo "\n⚠️  Errors:\n";
        foreach (array_slice($errors, 0, 10) as $err) {
            echo "   - $err\n";
        }
        if (count($errors) > 10) {
            echo "   ... and " . (count($errors) - 10) . " more\n";
        }
    }

    // Verify
    $pgCount = (int)$pg->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "\n📊 Verification: $pgCount products in PostgreSQL\n";

    // Show type breakdown
    echo "\n📈 By type:\n";
    $types = $pg->query("SELECT type, COUNT(*) as cnt FROM products GROUP BY type ORDER BY cnt DESC")->fetchAll();
    foreach ($types as $t) {
        echo "   - {$t['type']}: {$t['cnt']}\n";
    }

    echo "\n🎉 Done!\n";

} catch (Exception $e) {
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
        'audiobook' => 'audiobook',
        'course' => 'course',
        'video' => 'course',
    ];
    return $map[strtolower($type ?? 'post')] ?? 'book';
}

function generateSlug(?string $title, int $id, array $usedSlugs): string
{
    if (empty($title)) {
        return 'product-' . $id;
    }

    // Convert Persian/Arabic to slug-friendly
    $slug = mb_strtolower(trim($title));
    
    // Replace spaces with dash
    $slug = preg_replace('/\s+/u', '-', $slug);
    
    // Remove special characters but keep Persian/Arabic letters
    $slug = preg_replace('/[^\p{L}\p{N}\-]/u', '', $slug);
    
    // Remove multiple dashes
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Trim dashes
    $slug = trim($slug, '-');
    
    // Limit length
    if (mb_strlen($slug) > 200) {
        $slug = mb_substr($slug, 0, 200);
    }
    
    // If empty, use id
    if (empty($slug)) {
        $slug = 'product-' . $id;
    }
    
    // Ensure uniqueness
    $baseSlug = $slug;
    $counter = 1;
    while (isset($usedSlugs[$slug])) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}
