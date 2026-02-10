<?php

namespace App\Requests\Auth;

class EitaaLoginRequest
{
    public function __construct(
        private readonly string $eitaaData,
        private readonly array $deviceInfo
    ) {
    }

    public static function fromObject(object $request): self
    {
        $eitaaData = isset($request->eitaa_data) ? (string)$request->eitaa_data : '';

        return new self(
            eitaaData: $eitaaData,
            deviceInfo: [
                'device_name' => $request->device_name ?? null,
                'device_type' => $request->device_type ?? null,
                'platform' => $request->platform ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]
        );
    }

    public function getEitaaData(): string
    {
        return $this->eitaaData;
    }

    public function deviceInfo(): array
    {
        return $this->deviceInfo;
    }
}
