<?php

namespace App\DTOs;

class EitaaUserDTO
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $username,
        private readonly ?string $firstName,
        private readonly ?string $lastName,
        private readonly ?string $email,
        private readonly ?string $avatar
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            username: $data['username'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            email: $data['email'] ?? null,
            avatar: $data['photo_url'] ?? ($data['avatar'] ?? null)
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }
}
