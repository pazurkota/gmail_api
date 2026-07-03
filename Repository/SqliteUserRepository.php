<?php

namespace App\Repository;

use App\Database\Database;
use App\Interface\UserRepositoryInterface;

class SqliteUserRepository implements UserRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function upsert(array $user): void
    {
        $stmt = $this->db->pdo()->prepare("
            INSERT INTO users (gmail_email, messages_total, threads_total, last_sync)
            VALUES (:email, :messages_total, :threads_total, :last_sync)
            ON CONFLICT(gmail_email) DO UPDATE SET
                messages_total = excluded.messages_total,
                threads_total = excluded.threads_total,
                last_sync = excluded.last_sync
        ");
        $stmt->execute([
            'email' => $user['email'],
            'messages_total' => $user['messages_total'] ?? null,
            'threads_total' => $user['threads_total'] ?? null,
            'last_sync' => date('Y-m-d H:i:s'),
        ]);
    }
}