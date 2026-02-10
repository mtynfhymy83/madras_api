<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env if exists
if (file_exists(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->load();
}

// Sync environment variables
$envVars = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD'];
foreach ($envVars as $k) {
    if (!array_key_exists($k, $_ENV) && ($v = getenv($k)) !== false) {
        $_ENV[$k] = $v;
    }
}

// Database connection
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '5432';
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$user = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: '';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

if (empty($host) || empty($dbname) || empty($user)) {
    die("❌ Error: Database credentials not found in environment variables.\n");
}

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Connected to database: $dbname@$host:$port\n\n";
    
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage() . "\n");
}

// Create migrations table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id SERIAL PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

echo "📋 Checking migrations...\n\n";

// Get already executed migrations
$stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id");
$executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get migration files
$migrationsPath = __DIR__ . '/migrations';
if (!is_dir($migrationsPath)) {
    die("❌ Migrations directory not found: $migrationsPath\n");
}

$files = glob($migrationsPath . '/*.php');
sort($files);

$newMigrations = 0;

foreach ($files as $file) {
    $filename = basename($file);
    
    // Skip if already executed
    if (in_array($filename, $executedMigrations, true)) {
        echo "⏭️  Skipped: $filename (already executed)\n";
        continue;
    }
    
    echo "🔄 Running: $filename ... ";
    
    try {
        // Execute migration
        $sql = file_get_contents($file);
        
        // Remove PHP tags if present
        $sql = preg_replace('/<\?php.*?\?>/s', '', $sql);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s)
        );
        
        $pdo->beginTransaction();
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Record migration
        $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$filename]);
        
        $pdo->commit();
        
        echo "✅ Done\n";
        $newMigrations++;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "❌ Failed\n";
        echo "   Error: " . $e->getMessage() . "\n\n";
        
        // Ask to continue or stop
        if (PHP_SAPI === 'cli') {
            echo "Continue with next migration? [y/N]: ";
            $input = trim(fgets(STDIN));
            if (strtolower($input) !== 'y') {
                die("Migration stopped.\n");
            }
        } else {
            die("Migration stopped.\n");
        }
    }
}

echo "\n";
echo "🎉 Migration complete!\n";
echo "   New migrations: $newMigrations\n";
echo "   Total migrations: " . count($executedMigrations) + $newMigrations . "\n";
