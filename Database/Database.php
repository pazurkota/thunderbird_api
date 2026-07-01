<?php

namespace App\Database;

use PDO;

class Database
{
    public static function connect(string $dbFilePath): PDO
    {
        $pdo = new PDO('sqlite:' . $dbFilePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS accounts (
                tb_account_id TEXT PRIMARY KEY,
                account_name  TEXT NOT NULL,
                identities    TEXT NOT NULL DEFAULT "[]",
                last_sync     TEXT NOT NULL,
                status        TEXT NOT NULL DEFAULT "active"
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS messages (
                id            TEXT PRIMARY KEY,
                subject       TEXT NOT NULL DEFAULT "No subject",
                author        TEXT NOT NULL DEFAULT "Unknown",
                date          TEXT NOT NULL,
                body_preview  TEXT NOT NULL DEFAULT "",
                save_at       TEXT NOT NULL
            )
        ');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_date ON messages(date)');
    }
}
