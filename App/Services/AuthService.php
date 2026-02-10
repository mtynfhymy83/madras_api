<?php

namespace App\Services;

use App\Auth\JWTAuth;
use App\Contracts\AuthServiceInterface;
use App\DTOs\EitaaUserDTO;
use App\Database\DB;
use App\Requests\Auth\EitaaLoginRequest;
use App\Requests\Auth\LoginRequest;
use App\Resources\AuthTokenResource;
use Exception;
use PDO;
use PDOException;

class AuthService implements AuthServiceInterface
{
    use JWTAuth;

    private PDO $pdo;
    private ?string $botToken;
    
    // Static cache to persist across requests
    private static array $tableColumnsCache = [];
    private static array $userColumnMapCache = [];
    private array $userColumnMap = [];
    private array $userColumnCandidates = [
        'display_name' => ['display_name', 'displayname'],
        'mobile_number' => ['mobile_number', 'tel', 'phone'],
        'profile_image' => ['profile_image', 'avatar'],
        'role' => ['role', 'level'],
        'level' => ['level', 'role'],

        'status' => ['status', 'aproved'],
    ];

    public function __construct()
    {
        $this->pdo = DB::get();
        $this->botToken = $_ENV['EITAA_BOT_TOKEN'] ?? null;
        $this->initializeUserColumnMap();
    }

    public function loginWithCredentials(LoginRequest $request): array
    {
        $user = $this->findUserByCredentials($request);
        if (!$user) {
            throw new Exception('Username or mobile number not found');
        }


        $tokens = $this->issueTokens($user, $request->deviceInfo());
        $this->updateLastLogin((int)$user['id']);

        return $tokens;
    }

    public function authenticateWithEitaa(EitaaLoginRequest $request): array
    {
        if ($request->getEitaaData() === '') {
            throw new Exception('Eitaa data was not sent');
        }

        if (!$this->botToken) {
            throw new Exception('Eitaa bot token is not configured');
        }

        [$payloadParams, $userPayload] = $this->decodeEitaaPayload($request->getEitaaData());

        if (!$this->validateEitaaData($payloadParams, $this->botToken)) {
            throw new Exception('Eitaa data is invalid');
        }

        $eitaaUser = $this->parseEitaaData($userPayload);
        if ($eitaaUser->getId() === '') {
            throw new Exception('Eitaa user ID is invalid');
        }

        $user = $this->findUserByEitaaId($eitaaUser->getId());
        $isNewUser = false;

        if (!$user) {
            $user = $this->registerEitaaUser($eitaaUser);
            $isNewUser = true;
        }

        if (!$this->isActive($user)) {
            throw new Exception('Your account is inactive');
        }

        $tokens = $this->issueTokens($user, $request->deviceInfo());
        $tokens['is_new_user'] = $isNewUser;

        return $tokens;
    }

    public function logout(): bool
    {
        return true;
    }

    public function logoutFromAllDevices(int $userId): bool
    {
        return true;
    }

    public function getUserProfile(int $userId): ?array
    {
        $query = 'SELECT u.*, up.eitaa_id, up.first_name AS profile_name, up.last_name AS profile_family '
            . 'FROM users u '
            . 'LEFT JOIN user_profiles up ON up.user_id = u.id '
            . 'WHERE u.id = :id AND u.deleted_at IS NULL '
            . 'LIMIT 1';

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            // Fallback to users table if profile table does not exist
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$user) {
            return null;
        }

        return $this->formatUser($user);
    }

    /**
     * Verify JWT token
     * Wrapper method that uses verifyToken from JWTAuth trait
     * 
     * @param string $token
     * @return object|false
     */
    public function verifyToken(string $token)
    {
        try {
            $config = $this->loadJwtConfig();
            return \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($config['secret'], $config['algo']));
        } catch (\Exception $e) {
            return false;
        }
    }

    private function issueTokens(array $user, array $deviceInfo = []): array
    {
        $userId = (int)$user['id'];
        
        $roleService = new \App\Services\RoleService();
        $permissionService = new \App\Services\PermissionService();
        
        $highestRole = $roleService->getUserHighestRole($userId);
        
        if (!$highestRole) {
            $role = $this->getUserValue($user, 'role');
            $level = $this->getUserValue($user, 'level');
            
            if ($level !== null) {
                if ($level == '2' || $level == 2) {
                    $role = 'admin';
                } elseif (!$role) {
                    $role = 'guest';
                }
            } elseif (!$role) {
                $role = 'guest';
            }
            
            $permissions = [];
        } else {
            $role = $highestRole['name'];
            $level = $highestRole['priority'];
            
            $permissions = $permissionService->getUserPermissions($userId);
        }

        $claims = [
            'id' => $userId,
            'username' => $user['username'] ?? null,
            'mobile_number' => $this->getUserValue($user, 'mobile_number'),
            'role' => $role,
            'level' => $level ?? $role,
            'permissions' => $permissions,
            'status' => $this->getUserValue($user, 'status')
        ];

        $accessToken = $this->createJwt($claims);
        $accessTtlSeconds = ($this->loadJwtConfig()['ttl'] ?? 60) * 60;
        $expiresAt = date('c', time() + $accessTtlSeconds);

        return (new AuthTokenResource(
            accessToken: $accessToken,
            expiresInSeconds: $accessTtlSeconds,
            expiresAtIso: $expiresAt
        ))->toArray();
    }

    private function findUserByCredentials(LoginRequest $request): ?array
    {
        $username = trim($request->getUsername());
        $password = (string) $request->getPassword();

        if (empty($username) || empty($password)) {
            return null;
        }

        try {
            // Try to find by username first
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // If not found, try case-insensitive username
            if (!$user) {
                $stmt = $this->pdo->prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(:username) AND deleted_at IS NULL LIMIT 1');
                $stmt->execute(['username' => $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // If still not found, try mobile number (username might be mobile)
            if (!$user) {
                $stmt = $this->pdo->prepare('SELECT * FROM users WHERE mobile = :mobile AND deleted_at IS NULL LIMIT 1');
                $stmt->execute(['mobile' => $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$user) {
                return null;
            }

//            if (!isset($user['role']) || $user['role'] !== 'admin') {
//                throw new \App\Exceptions\AccessDeniedException("You do not have access to the admin panel.");
//            }

            if (!isset($user['password']) || empty($user['password'])) {
                return null;
            }

            $passwordHash = trim($user['password']);
            $isPasswordValid = false;

            if (strpos($passwordHash, '$2y$') === 0 || strpos($passwordHash, '$argon2') === 0) {
                $isPasswordValid = password_verify($password, $passwordHash);
            }
            elseif (strlen($passwordHash) === 40 && ctype_xdigit($passwordHash)) {
                $isPasswordValid = hash_equals(strtolower($passwordHash), sha1($password));
            }
            elseif (strlen($passwordHash) === 64 && ctype_xdigit($passwordHash)) {
                $isPasswordValid = hash_equals(strtolower($passwordHash), hash('sha256', $password));
            }
            else {
                $isPasswordValid = hash_equals($passwordHash, $password);
            }

            if (!$isPasswordValid) {
                return null;
            }

            return $user;

        } catch (PDOException $e) {
            return null;
        }
    }

    private function findUserByEitaaId(string $eitaaId): ?array
    {
        try {
            $query = 'SELECT u.*, up.eitaa_id, up.first_name AS profile_name, up.last_name AS profile_family '
                . 'FROM users u '
                . 'INNER JOIN user_profiles up ON up.user_id = u.id '
                . 'WHERE up.eitaa_id = :eitaa_id AND u.deleted_at IS NULL '
                . 'LIMIT 1';

            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['eitaa_id' => $eitaaId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;
        } catch (PDOException $exception) {
            // If tables don't exist, return null to allow user registration
            error_log("[AuthService] Error finding user by Eitaa ID: " . $exception->getMessage());
            return null;
        }
    }

    private function registerEitaaUser(EitaaUserDTO $eitaaUser): array
    {
        $username = $eitaaUser->getUsername() ?? ('user_' . $eitaaUser->getId());
        $firstName = $eitaaUser->getFirstName() ?? '';
        $lastName = $eitaaUser->getLastName() ?? '';
        $email = $eitaaUser->getEmail() ?? ($username . '@eitaa.local');
        $displayName = trim($firstName . ' ' . $lastName) ?: $username;
        $now = date('Y-m-d H:i:s');

        $displayColumn = $this->getUserColumn('display_name');
        $mobileColumn = $this->getUserColumn('mobile_number');
        $profileImageColumn = $this->getUserColumn('profile_image');
        $roleColumn = $this->getUserColumn('role');
        $statusColumn = $this->getUserColumn('status');

        try {
            $this->pdo->beginTransaction();

            $columns = ['username'];
            $placeholders = [':username'];
            $params = ['username' => $username];

            if ($displayColumn) {
                $columns[] = $displayColumn;
                $placeholders[] = ':' . $displayColumn;
                $params[$displayColumn] = $displayName;
            }

            if ($mobileColumn) {
                $columns[] = $mobileColumn;
                $placeholders[] = ':' . $mobileColumn;
                $params[$mobileColumn] = null;
            }

            if ($profileImageColumn) {
                $columns[] = $profileImageColumn;
                $placeholders[] = ':' . $profileImageColumn;
                $params[$profileImageColumn] = $eitaaUser->getAvatar();
            }

            if ($roleColumn) {
                $columns[] = $roleColumn;
                $placeholders[] = ':' . $roleColumn;
                $params[$roleColumn] = $roleColumn === 'level' ? 1 : 'guest';
            }

            if ($statusColumn) {
                $columns[] = $statusColumn;
                $placeholders[] = ':' . $statusColumn;
                $params[$statusColumn] = $this->normalizeStatusValue('accept', $statusColumn);
            }

            $columns[] = 'email';
            $placeholders[] = ':email';
            $params['email'] = $email;

            $columns[] = 'password';
            $placeholders[] = ':password';
            $params['password'] = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

            $columns[] = 'created_at';
            $placeholders[] = ':created_at';
            $params['created_at'] = $now;

            $columns[] = 'updated_at';
            $placeholders[] = ':updated_at';
            $params['updated_at'] = $now;

            $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $userId = (int)$this->pdo->lastInsertId();

            $stmtProfile = $this->pdo->prepare('INSERT INTO user_profiles (user_id, eitaa_id, first_name, last_name, created_at, updated_at) '
                . 'VALUES (:user_id, :eitaa_id, :first_name, :last_name, :created_at, :updated_at)');

            $stmtProfile->execute([
                'user_id' => $userId,
                'eitaa_id' => $eitaaUser->getId(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            if (strpos($exception->getMessage(), 'does not exist') !== false || 
                strpos($exception->getMessage(), 'relation') !== false) {
                throw new Exception('Database error: required tables do not exist. Please run migrations first.');
            }
            error_log("[AuthService] Database error in registerEitaaUser: " . $exception->getMessage());
            throw new Exception('User registration failed: ' . $exception->getMessage());
        } catch (Exception $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $userData = [
            'id' => $userId,
            'username' => $username
        ];

        if ($displayColumn) {
            $userData[$displayColumn] = $displayName;
        }

        if ($mobileColumn) {
            $userData[$mobileColumn] = null;
        }

        if ($profileImageColumn) {
            $userData[$profileImageColumn] = $eitaaUser->getAvatar();
        }

        if ($roleColumn) {
            $userData[$roleColumn] = $roleColumn === 'level' ? 1 : 'guest';
        }

        if ($statusColumn) {
            $userData[$statusColumn] = $this->normalizeStatusValue('accept', $statusColumn);
        }

        return $userData;
    }

    private function validateEitaaData(array $params, string $botToken): bool
    {
        if (!isset($params['hash'])) {
            return false;
        }

        $receivedHash = $params['hash'];
        unset($params['hash']);

        ksort($params);

        $dataCheckArray = [];
        foreach ($params as $key => $value) {
            $dataCheckArray[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckArray);

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($calculatedHash, $receivedHash);
    }

    private function parseEitaaData(array $userData): EitaaUserDTO
    {
        return EitaaUserDTO::fromArray($userData ?? []);
    }

    private function decodeEitaaPayload(string $eitaaData): array
    {
        parse_str($eitaaData, $params);
        $userData = json_decode($params['user'] ?? '{}', true);

        return [$params, $userData ?? []];
    }

    private function updateLastLogin(int $userId): void
    {
        $columns = $this->getTableColumns('users');
        if (!isset($columns['last_login_at'])) {
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = :last_login_at WHERE id = :id');
            $stmt->execute([
                'last_login_at' => date('Y-m-d H:i:s'),
                'id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("[AuthService] Could not update last_login_at: " . $e->getMessage());
        }
    }

    private function formatUser(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'display_name' => $this->getUserValue($user, 'display_name') ?? ($user['name'] ?? null),
            'username' => $user['username'] ?? null,
            'mobile_number' => $this->getUserValue($user, 'mobile_number') ?? ($user['phone'] ?? null),
            'role' => $this->getUserValue($user, 'role') ?? 'guest',
            'status' => $this->getUserValue($user, 'status'),
            'avatar' => $this->getUserValue($user, 'profile_image'),
            'last_login_at' => $user['last_login_at'] ?? null,
            'profile' => [
                'eitaa_id' => $user['eitaa_id'] ?? null,
                'name' => $user['profile_name'] ?? null,
                'family' => $user['profile_family'] ?? null,
            ]
        ];
    }

    private function initializeUserColumnMap(): void
    {
        // Use static cache to avoid repeated schema queries
        if (!empty(self::$userColumnMapCache)) {
            $this->userColumnMap = self::$userColumnMapCache;
            return;
        }
        
        $columns = $this->getTableColumns('users');
        foreach ($this->userColumnCandidates as $key => $candidates) {
            $this->userColumnMap[$key] = $this->matchColumn($columns, $candidates);
        }
        
        // Cache for future requests
        self::$userColumnMapCache = $this->userColumnMap;
    }

    private function getTableColumns(string $table): array
    {
        // Use static cache for persistence across requests
        if (isset(self::$tableColumnsCache[$table])) {
            return self::$tableColumnsCache[$table];
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [];

        try {
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query("PRAGMA table_info({$table})");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                foreach ($rows as $row) {
                    $name = $row['name'];
                    $columns[strtolower($name)] = $name;
                }
            } else {
                $sql = 'SELECT column_name FROM information_schema.columns WHERE table_name = :table';
                if ($driver === 'mysql') {
                    $sql .= ' AND table_schema = DATABASE()';
                } elseif ($driver === 'pgsql') {
                    $sql .= ' AND table_schema = current_schema()';
                }
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['table' => $table]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $name = $row['column_name'];
                    $columns[strtolower($name)] = $name;
                }
            }
        } catch (PDOException $exception) {
            $columns = [];
        }

        // Store in static cache
        self::$tableColumnsCache[$table] = $columns;
        return $columns;
    }

    private function matchColumn(array $available, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $lower = strtolower($candidate);
            if (isset($available[$lower])) {
                return $available[$lower];
            }
        }

        return null;
    }

    private function getUserColumn(string $key): ?string
    {
        return $this->userColumnMap[$key] ?? null;
    }

    private function getUserValue(array $user, string $key, $default = null)
    {
        $column = $this->getUserColumn($key);
        if ($column && array_key_exists($column, $user)) {
            return $user[$column];
        }

        foreach ($this->userColumnCandidates[$key] ?? [] as $candidate) {
            if (array_key_exists($candidate, $user)) {
                return $user[$candidate];
            }
        }

        return $default;
    }

    private function normalizeStatusValue(string $status, ?string $column)
    {
        if ($column === 'aproved') {
            return in_array($status, ['accept', 'active', 1, true], true) ? 1 : 0;
        }

        return $status;
    }

    private function isActive(array $user): bool
    {
        return !isset($user['status']) || in_array($user['status'], ['accept', 'active', 1, true], true);
    }
}
