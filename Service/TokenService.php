<?php

namespace App\Service;

class TokenService
{
    public function __construct(private string $expectedToken) {}

    public function isValid(?string $providedToken): bool
    {
        return $providedToken !== null && hash_equals($this->expectedToken, $providedToken);
    }
}