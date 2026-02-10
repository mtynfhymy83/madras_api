<?php

namespace App\Resources;

class AuthTokenResource
{
    public function __construct(
        private string $accessToken,
        private int $expiresInSeconds,
        private string $expiresAtIso,
        private string $tokenType = 'Bearer',
        private array $meta = []
    ) {
    }

    public function withMeta(array $meta): self
    {
        $clone = clone $this;
        $clone->meta = array_merge($clone->meta, $meta);
        return $clone;
    }

    public function toArray(): array
    {
        return array_merge([
            'success' => true,
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresInSeconds,
            'expires_at' => $this->expiresAtIso,
        ], $this->meta);
    }
}
