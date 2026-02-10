<?php

namespace App\Requests\Auth;

class LoginRequest
{
    public function __construct(
        private readonly ?string $identifier,
        private readonly ?string $username,
        private readonly ?string $mobileNumber,
        private readonly ?string $password,
        private readonly array $deviceInfo
    ) {
    }

    public static function fromObject(object $request): self
    {
        return new self(
            identifier: isset($request->identifier) ? trim((string)$request->identifier) : null,
            username: isset($request->username) ? trim((string)$request->username) : null,
            mobileNumber: isset($request->mobile_number) ? trim((string)$request->mobile_number) : null,
            password: isset($request->password) ? (string)$request->password : null,
            deviceInfo: [
                'device_name' => $request->device_name ?? null,
                'device_type' => $request->device_type ?? null,
                'platform' => $request->platform ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]
        );
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getMobileNumber(): ?string
    {
        return $this->mobileNumber;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function deviceInfo(): array
    {
        return $this->deviceInfo;
    }
}
