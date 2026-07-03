<?php

namespace App\Service;

class TokenResetService
{
    public function __construct(private EnvService $envService) {}

    public function reset(): string
    {
        $newToken = bin2hex(random_bytes(32));
        $this->envService->writeNewToken($newToken);
        return $newToken;
    }
}