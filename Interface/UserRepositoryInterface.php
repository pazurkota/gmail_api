<?php

namespace App\Interface;

interface UserRepositoryInterface
{
    public function upsert(array $user): void;
}