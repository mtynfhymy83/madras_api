<?php
namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

trait JWTAuth {
    private array $jwtConfig = [];

    private function loadJwtConfig(): array
    {
        if (empty($this->jwtConfig)) {
            $this->jwtConfig = [
                'secret' => $_ENV['JWT_SECRET'] ?? $_ENV['SECRET_KEY'] ?? '',
                'algo' => $_ENV['JWT_ALGO'] ?? 'HS256',
                'ttl' => (int)($_ENV['JWT_TTL'] ?? 60)
            ];
        }

        return $this->jwtConfig;
    }

    protected function createJwt(array $claims, ?int $ttlMinutes = null): string
    {
        $config = $this->loadJwtConfig();
        $ttl = $ttlMinutes ?? $config['ttl'];
        $now = time();

        $payload = array_merge([
            'iat' => $now,
            'exp' => $now + ($ttl * 60)
        ], $claims);

        return JWT::encode($payload, $config['secret'], $config['algo']);
    }

    public function generateToken($id, $username = null, $mobile_number = null, $role = null, $status = null)
    {
        if (is_array($id)) {
            $claims = $id;
        } else {
            $claims = [
                'id' => $id,
                'username' => $username,
                'mobile_number' => $mobile_number,
                'role' => $role,
                'status' => $status
            ];
        }

        return $this->createJwt($claims);
    }

    public function verifyToken($token)
    {
        try {
            $config = $this->loadJwtConfig();
            return JWT::decode($token, new Key($config['secret'], $config['algo']));
        } catch (\Exception $e) {
            return false;
        }
    }
}