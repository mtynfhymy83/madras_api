<?php
/**
 * Users Migration Script
 * Migrate users from MySQL (ci_users) to PostgreSQL (users + user_profiles)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->load();
}

// MySQL config (old database)
$mysqlConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'madras',
    'user' => 'root',
    'pass' => 'pass'
];

// PostgreSQL config (new database)
$pgConfig = [
    'host' => 'localhost',
    'port' => 5432,
    'dbname' => 'madras',
    'user' => 'myuser',
    'pass' => 'mypass'
];

$chunkSize = 500;

echo "🚀 Users Migration Script\n";
echo "==========================\n\n";

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

    // Clear existing data for fresh migration
    echo "🗑️  Clearing existing users...\n";
    $pg->exec("TRUNCATE TABLE user_profiles, users RESTART IDENTITY CASCADE");
    echo "   ✅ Cleared\n\n";

    // Count total users
    $totalUsers = (int)$mysql->query("SELECT COUNT(*) FROM ci_users")->fetchColumn();
    echo "📊 Found $totalUsers users to migrate\n\n";

    if ($totalUsers === 0) {
        echo "⚠️  No users found in source database\n";
        exit(0);
    }

    // Prepare PostgreSQL statements
    $stmtUser = $pg->prepare("
        INSERT INTO users (old_id, username, mobile, email, password, role, status, created_at, updated_at)
        VALUES (:old_id, :username, :mobile, :email, :password, :role, :status, :created_at, :updated_at)
        ON CONFLICT (old_id) DO UPDATE SET
            username = EXCLUDED.username,
            mobile = EXCLUDED.mobile,
            email = EXCLUDED.email,
            password = EXCLUDED.password,
            role = EXCLUDED.role,
            status = EXCLUDED.status,
            updated_at = NOW()
        RETURNING id
    ");

    $stmtProfile = $pg->prepare("
        INSERT INTO user_profiles (user_id, first_name, last_name, full_name, national_code, gender, birth_date, 
            country, province, city, postal_code, address, avatar_path, cover_path, created_at, updated_at)
        VALUES (:user_id, :first_name, :last_name, :full_name, :national_code, :gender, :birth_date,
            :country, :province, :city, :postal_code, :address, :avatar_path, :cover_path, :created_at, :updated_at)
        ON CONFLICT (user_id) DO UPDATE SET
            first_name = EXCLUDED.first_name,
            last_name = EXCLUDED.last_name,
            full_name = EXCLUDED.full_name,
            national_code = EXCLUDED.national_code,
            gender = EXCLUDED.gender,
            birth_date = EXCLUDED.birth_date,
            country = EXCLUDED.country,
            province = EXCLUDED.province,
            city = EXCLUDED.city,
            postal_code = EXCLUDED.postal_code,
            address = EXCLUDED.address,
            avatar_path = EXCLUDED.avatar_path,
            cover_path = EXCLUDED.cover_path,
            updated_at = NOW()
    ");

    $offset = 0;
    $migrated = 0;
    $skipped = 0;
    $errors = [];

    echo "⚙️  Migrating users...\n";

    while ($offset < $totalUsers) {
        $rows = $mysql->query("SELECT * FROM ci_users ORDER BY id LIMIT $chunkSize OFFSET $offset")->fetchAll();

        foreach ($rows as $row) {
            // Map role from level
            $role = mapRole($row['level'] ?? 'user');
            
            // Map status from active
            $status = ($row['active'] ?? 1) == 1 ? 'active' : 'inactive';
            
            // Convert date
            $createdAt = $row['date'] ?? date('Y-m-d H:i:s');
            if (is_numeric($createdAt)) {
                $createdAt = date('Y-m-d H:i:s', (int)$createdAt);
            }

            // Clean fields
            $mobile = cleanMobile($row['tel'] ?? null);
            $email = cleanEmail($row['email'] ?? null);
            $username = cleanUsername($row['username'] ?? null);

            // Convert birthday to date
            $birthDate = convertBirthday($row['birthday'] ?? null);

            // Build full_name
            $fullName = trim(($row['name'] ?? '') . ' ' . ($row['family'] ?? ''));
            if (empty($fullName)) {
                $fullName = $row['displayname'] ?? null;
            }

            // Try insert with retries (nullify duplicate fields)
            $maxRetries = 3;
            $retry = 0;
            $inserted = false;

            while (!$inserted && $retry < $maxRetries) {
                try {
                    $pg->beginTransaction();

                    $stmtUser->execute([
                        'old_id' => $row['id'],
                        'username' => $username,
                        'mobile' => $mobile,
                        'email' => $email,
                        'password' => $row['password'] ?? null,
                        'role' => $role,
                        'status' => $status,
                        'created_at' => $createdAt,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    $newUserId = $stmtUser->fetchColumn();

                    $stmtProfile->execute([
                        'user_id' => $newUserId,
                        'first_name' => $row['name'] ?? null,
                        'last_name' => $row['family'] ?? null,
                        'full_name' => $fullName ?: null,
                        'national_code' => $row['national_code'] ?? null,
                        'gender' => (int)($row['gender'] ?? 1),
                        'birth_date' => $birthDate,
                        'country' => $row['country'] ?? 'Iran',
                        'province' => $row['state'] ?? null,
                        'city' => $row['city'] ?? null,
                        'postal_code' => $row['postal_code'] ?? null,
                        'address' => $row['address'] ?? null,
                        'avatar_path' => $row['avatar'] ?? null,
                        'cover_path' => $row['cover'] ?? null,
                        'created_at' => $createdAt,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    $pg->commit();
                    $migrated++;
                    $inserted = true;

                } catch (PDOException $e) {
                    $pg->rollBack();
                    $retry++;
                    $errMsg = $e->getMessage();

                    // Check which constraint failed and nullify that field
                    if (strpos($errMsg, 'users_email_key') !== false) {
                        $email = null; // Nullify duplicate email
                    } elseif (strpos($errMsg, 'users_mobile_key') !== false) {
                        $mobile = null; // Nullify duplicate mobile
                    } elseif (strpos($errMsg, 'users_username_key') !== false) {
                        $username = null; // Nullify duplicate username (shouldn't happen)
                    } else {
                        // Unknown error, don't retry
                        $retry = $maxRetries;
                        if (count($errors) < 100) {
                            $errors[] = "ID {$row['id']}: " . substr($errMsg, 0, 100);
                        }
                    }
                }
            }

            if (!$inserted) {
                $skipped++;
            }
        }

        $offset += $chunkSize;
        $percent = min(100, round(($offset / $totalUsers) * 100));
        echo "\r   Progress: $migrated migrated, $skipped skipped ($percent%)      ";
    }

    echo "\n\n";
    echo "=============================\n";
    echo "✅ Migration completed!\n";
    echo "   Migrated: $migrated users\n";
    
    if (!empty($errors)) {
        echo "   Errors: " . count($errors) . "\n\n";
        echo "⚠️  Errors:\n";
        foreach (array_slice($errors, 0, 10) as $err) {
            echo "   - $err\n";
        }
        if (count($errors) > 10) {
            echo "   ... and " . (count($errors) - 10) . " more\n";
        }
    }

    // Verify
    $pgCount = (int)$pg->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "\n📊 Verification: $pgCount users in PostgreSQL\n";

    echo "\n🎉 Done!\n";

} catch (Exception $e) {
    echo "\n❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Helper functions
function mapRole(?string $level): string
{
    $map = [
        'admin' => 'admin',
        'support' => 'support',
        'user' => 'user',
        'guest' => 'user',
        '1' => 'admin',
        '2' => 'support',
        '3' => 'user',
    ];
    return $map[strtolower($level ?? 'user')] ?? 'user';
}

function cleanMobile(?string $tel): ?string
{
    if (empty($tel)) return null;
    
    // Remove non-digits
    $tel = preg_replace('/[^0-9]/', '', $tel);
    
    // Convert to standard format
    if (strlen($tel) === 10 && $tel[0] === '9') {
        $tel = '0' . $tel;
    }
    if (strlen($tel) === 12 && str_starts_with($tel, '98')) {
        $tel = '0' . substr($tel, 2);
    }
    
    // Validate Iranian mobile
    if (strlen($tel) === 11 && str_starts_with($tel, '09')) {
        return $tel;
    }
    
    return null; // Invalid mobile
}

function cleanEmail(?string $email): ?string
{
    if (empty($email)) return null;
    
    $email = trim(strtolower($email));
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    
    return null;
}

function cleanUsername(?string $username): ?string
{
    if (empty($username)) return null;
    
    $username = trim($username);
    
    // Remove invalid characters
    $username = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $username);
    
    if (strlen($username) < 2) return null;
    
    return $username;
}

function convertBirthday(?string $birthday): ?string
{
    if (empty($birthday)) return null;
    
    // Try different formats
    // Format: 1370/01/15 (Jalali)
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $birthday, $m)) {
        $year = (int)$m[1];
        $month = (int)$m[2];
        $day = (int)$m[3];
        
        // Check if Jalali (years 1300-1450)
        if ($year >= 1300 && $year <= 1450) {
            // Convert Jalali to Gregorian (simplified)
            $gYear = $year + 621;
            if ($month > 6) $gYear++;
            return sprintf('%04d-%02d-%02d', $gYear, $month, $day);
        }
        
        // Already Gregorian
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    
    // Try timestamp
    if (is_numeric($birthday) && $birthday > 0) {
        return date('Y-m-d', (int)$birthday);
    }
    
    return null;
}
