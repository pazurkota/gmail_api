<?php

namespace App\Database;

use PDO;

class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                gmail_email TEXT PRIMARY KEY,
                messages_total INTEGER,
                threads_total INTEGER,
                last_sync TEXT
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS messages (
                id TEXT PRIMARY KEY,
                user_email TEXT,
                subject TEXT,
                sender TEXT,
                snippet TEXT,
                date TEXT,
                save_at TEXT
            )
        ");
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}