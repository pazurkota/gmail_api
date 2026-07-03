<?php

namespace App\Service;

use App\Interface\MessageRepositoryInterface;

class MessageSyncService
{
    private const KEEP_LATEST = 10;

    public function __construct(private MessageRepositoryInterface $repository) {}

    public function sync(array $messages, string $userEmail): int
    {
        $saved = $this->repository->upsertMany($messages, $userEmail);
        $this->repository->pruneToLatest($userEmail, self::KEEP_LATEST);
        return $saved;
    }
}