<?php
/**
 * Create book_contents table in PostgreSQL
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// PostgreSQL configuration
$pgConfig = [
    'host' => 'localhost',
    'port' => 5432,
    'dbname' => 'madras',
    'user' => 'myuser',
    'pass' => 'mypass'
];

echo "===========================================\n";
echo "📦 Creating book_contents table\n";
echo "===========================================\n\n";

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

// Check if table exists
$stmt = $pg->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'book_contents')");
$exists = $stmt->fetchColumn();

if ($exists) {
    echo "⚠️  Table book_contents already exists.\n";
    echo "   Drop and recreate? [y/N]: ";
    
    if (in_array('--force', $argv ?? [])) {
        $answer = 'y';
    } else {
        $answer = trim(fgets(STDIN));
    }
    
    if (strtolower($answer) !== 'y') {
        echo "   Skipping table creation.\n";
        exit(0);
    }
    
    echo "   Dropping existing table...\n";
    $pg->exec("DROP TABLE IF EXISTS book_contents CASCADE");
}

echo "📋 Creating table...\n";

// Create table
$sql = "
CREATE TABLE book_contents (
    id SERIAL PRIMARY KEY,
    book_id INTEGER NOT NULL,
    page_number INTEGER NOT NULL DEFAULT 1,
    paragraph_number INTEGER NOT NULL DEFAULT 1,
    \"order\" INTEGER NOT NULL DEFAULT 0,
    text TEXT,
    description TEXT,
    sound_path VARCHAR(500),
    image_paths TEXT,
    video_path TEXT,
    is_index BOOLEAN DEFAULT FALSE,
    index_title VARCHAR(300),
    index_level INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Indexes for performance
CREATE INDEX book_contents_book_id_idx ON book_contents(book_id);
CREATE INDEX book_contents_book_page_idx ON book_contents(book_id, page_number);
CREATE INDEX book_contents_book_page_para_idx ON book_contents(book_id, page_number, paragraph_number);
CREATE INDEX book_contents_book_order_idx ON book_contents(book_id, \"order\");
CREATE INDEX book_contents_book_index_idx ON book_contents(book_id, is_index) WHERE is_index = true;
CREATE INDEX book_contents_deleted_idx ON book_contents(deleted_at) WHERE deleted_at IS NULL;

-- Unique constraint
CREATE UNIQUE INDEX book_contents_unique_idx ON book_contents(book_id, page_number, paragraph_number) WHERE deleted_at IS NULL;
";

try {
    $pg->exec($sql);
    echo "✅ Table book_contents created successfully!\n";
} catch (PDOException $e) {
    die("❌ Failed to create table: " . $e->getMessage() . "\n");
}

// Add full-text search (optional, may fail if pg_trgm not installed)
echo "\n📋 Adding full-text search...\n";

try {
    $pg->exec("ALTER TABLE book_contents ADD COLUMN IF NOT EXISTS tsv tsvector");
    
    $pg->exec("
        CREATE OR REPLACE FUNCTION book_contents_tsv_trigger() RETURNS trigger AS \$\$
        BEGIN
            NEW.tsv := to_tsvector('simple', COALESCE(NEW.text, ''));
            RETURN NEW;
        END
        \$\$ LANGUAGE plpgsql
    ");
    
    $pg->exec("
        DROP TRIGGER IF EXISTS book_contents_tsv_update ON book_contents;
        CREATE TRIGGER book_contents_tsv_update 
        BEFORE INSERT OR UPDATE ON book_contents
        FOR EACH ROW EXECUTE FUNCTION book_contents_tsv_trigger()
    ");
    
    $pg->exec("CREATE INDEX IF NOT EXISTS book_contents_tsv_idx ON book_contents USING gin(tsv)");
    
    echo "✅ Full-text search enabled!\n";
} catch (PDOException $e) {
    echo "⚠️  Full-text search setup failed (optional): " . $e->getMessage() . "\n";
}

// Try to add trigram search
try {
    $pg->exec("CREATE EXTENSION IF NOT EXISTS pg_trgm");
    $pg->exec("CREATE INDEX IF NOT EXISTS book_contents_text_trgm_idx ON book_contents USING gin(text gin_trgm_ops)");
    echo "✅ Trigram search enabled!\n";
} catch (PDOException $e) {
    echo "⚠️  Trigram search setup failed (optional): " . $e->getMessage() . "\n";
}

echo "\n===========================================\n";
echo "✅ Done! Table is ready for migration.\n";
echo "===========================================\n";
