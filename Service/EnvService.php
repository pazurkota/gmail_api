<?php

namespace App\Service;

class EnvService
{
    public function __construct(private string $envPath) {}

    public function currentToken(): ?string
    {
        return $_ENV['GMAIL_API_TOKEN'] ?? getenv('GMAIL_API_TOKEN') ?: null;
    }

    public function writeNewToken(string $newToken): void
    {
        $lines = is_file($this->envPath)
            ? file($this->envPath, FILE_IGNORE_NEW_LINES)
            : [];

        $found = false;
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, 'GMAIL_API_TOKEN=')) {
                $lines[$i] = "GMAIL_API_TOKEN={$newToken}";
                $found = true;
                break;
            }
        }
        if (!$found) {
            $lines[] = "GMAIL_API_TOKEN={$newToken}";
        }

        file_put_contents($this->envPath, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);

        $_ENV['GMAIL_API_TOKEN'] = $newToken;
        putenv("GMAIL_API_TOKEN={$newToken}");
    }
}