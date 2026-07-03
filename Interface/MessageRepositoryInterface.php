<?php

namespace App\Interface;

interface MessageRepositoryInterface
{
    public function upsertMany(array $messages, string $userEmail): int;
    public function pruneToLatest(string $userEmail, int $limit): void;
}