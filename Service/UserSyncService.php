<?php

namespace App\Service;

use App\Interface\UserRepositoryInterface;

class UserSyncService
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function sync(array $userPayload): void
    {
        if (empty($userPayload['email'])) {
            throw new \InvalidArgumentException('Brak adresu email użytkownika.');
        }
        $this->repository->upsert($userPayload);
    }
}