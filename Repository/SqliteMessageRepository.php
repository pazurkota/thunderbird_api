<?php

namespace App\Repositories;

use PDO;

class SqliteMessageRepository implements MessageRepositoryInterface
{
    private PDO $pdo;
    private const MAX_MESSAGES = 10;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function saveLastestMessages(array $messages): int
    {
        $existsStmt = $this->pdo->prepare('SELECT 1 FROM messages WHERE id = :id');
        $upsertStmt = $this->pdo->prepare('
            INSERT INTO messages (id, subject, author, date, body_preview, save_at)
            VALUES (:id, :subject, :author, :date, :body_preview, :save_at)
            ON CONFLICT(id) DO UPDATE SET
                subject      = excluded.subject,
                author       = excluded.author,
                date         = excluded.date,
                body_preview = excluded.body_preview,
                save_at      = excluded.save_at
        ');

        $newCount = 0;

        foreach ($messages as $msg) {
            $msgId = $msg['id'] ?? null;
            if (!$msgId) {
                continue;
            }

            $existsStmt->execute([':id' => $msgId]);
            if (!$existsStmt->fetchColumn()) {
                $newCount++;
            }

            $upsertStmt->execute([
                ':id'           => $msgId,
                ':subject'      => $msg['subject'] ?? 'No subject',
                ':author'       => $msg['author'] ?? 'Unknown',
                ':date'         => $msg['date'] ?? date('Y-m-d H:i:s'),
                ':body_preview' => $msg['body_preview'] ?? '',
                ':save_at'      => date('Y-m-d, H:i:s'),
            ]);
        }

        $this->pdo->exec('
            DELETE FROM messages WHERE id NOT IN (
                SELECT id FROM messages ORDER BY date DESC LIMIT ' . self::MAX_MESSAGES . '
            )
        ');

        return $newCount;
    }
}
