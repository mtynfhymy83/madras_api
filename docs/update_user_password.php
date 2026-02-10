<?php
/**
 * One-off: set password for user "balvardi" to "123456" (SHA1).
 * Run from project root: php update_user_password.php
 */
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$projectRoot = __DIR__;
if (file_exists($projectRoot . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->load();
}

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '5432';
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'madras';
$user = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'myuser';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'mypass';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
$username = 'balvardi';
$newPasswordPlain = '123456';
$passwordHash = sha1($newPasswordPlain);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE username = :username');
    $stmt->execute(['password' => $passwordHash, 'username' => $username]);
    $rows = $stmt->rowCount();

    if ($rows > 0) {
        echo "Password updated for user '$username'. You can login with password: $newPasswordPlain\n";
    } else {
        echo "No user found with username '$username'. Check the username.\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
