<?php

namespace App\Resources;

class UserResource
{
    public function __construct(private array $payload)
    {
    }

    public static function make(array $payload): self
    {
        return new self($payload);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->payload['id'] ?? null,
            'display_name' => $this->payload['display_name'] ?? null,
            'username' => $this->payload['username'] ?? null,
            'mobile_number' => $this->payload['mobile_number'] ?? null,
            'role' => $this->payload['role'] ?? null,
            'status' => $this->payload['status'] ?? null,
            'avatar' => $this->payload['avatar'] ?? null,
            'last_login_at' => $this->payload['last_login_at'] ?? null,
            'profile' => $this->payload['profile'] ?? null,
        ];
    }
}
