<?php

namespace App\Repository;

use App\Database\Database;
use App\Interface\MessageRepositoryInterface;

class SqliteMessageRepository implements MessageRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function upsertMany(array $messages, string $userEmail): int
    {
        $stmt = $this->db->pdo()->prepare("
            INSERT INTO messages (id, user_email, subject, sender, snippet, date, save_at)
            VALUES (:id, :user_email, :subject, :sender, :snippet, :date, :save_at)
            ON CONFLICT(id) DO UPDATE SET
                subject = excluded.subject,
                sender = excluded.sender,
                snippet = excluded.snippet,
                date = excluded.date
        ");

        $saved = 0;
        foreach ($messages as $message) {
            $stmt->execute([
                'id' => $message['id'],
                'user_email' => $userEmail,
                'subject' => $message['subject'] ?? '',
                'sender' => $message['sender'] ?? '',
                'snippet' => $message['snippet'] ?? '',
                'date' => $message['date'] ?? '',
                'save_at' => date('Y-m-d H:i:s'),
            ]);
            $saved += $stmt->rowCount();
        }

        return $saved;
    }

    public function pruneToLatest(string $userEmail, int $limit): void
    {
        $stmt = $this->db->pdo()->prepare("
            DELETE FROM messages
            WHERE user_email = :user_email
            AND id NOT IN (
                SELECT id FROM messages
                WHERE user_email = :user_email2
                ORDER BY date DESC
                LIMIT :limit
            )
        ");
        $stmt->bindValue(':user_email', $userEmail);
        $stmt->bindValue(':user_email2', $userEmail);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
    }
}