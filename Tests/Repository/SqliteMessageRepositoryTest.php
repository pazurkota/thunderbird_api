<?php

namespace App\Tests\Repository;

use App\Database\Database;
use App\Repositories\SqliteMessageRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class SqliteMessageRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SqliteMessageRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = Database::connect(':memory:');
        $this->repository = new SqliteMessageRepository($this->pdo);
    }

    public function testSaveLastestMessagesInsertsNewMessages(): void
    {
        $newCount = $this->repository->saveLastestMessages([
            ['id' => 'msg-1', 'subject' => 'Hello', 'author' => 'Alice', 'date' => '2026-06-01 10:00:00', 'body_preview' => 'Hi there'],
            ['id' => 'msg-2', 'subject' => 'World', 'author' => 'Bob', 'date' => '2026-06-02 10:00:00', 'body_preview' => 'Yo'],
        ]);

        $this->assertSame(2, $newCount);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn());
    }

    public function testSaveLastestMessagesSkipsEntriesMissingId(): void
    {
        $newCount = $this->repository->saveLastestMessages([
            ['subject' => 'No id here'],
            ['id' => 'msg-1', 'subject' => 'Has id', 'date' => '2026-06-01 10:00:00'],
        ]);

        $this->assertSame(1, $newCount);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn());
    }

    public function testSaveLastestMessagesAppliesDefaultsForMissingFields(): void
    {
        $this->repository->saveLastestMessages([
            ['id' => 'msg-1'],
        ]);

        $stmt = $this->pdo->query('SELECT subject, author, body_preview FROM messages WHERE id = "msg-1"');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('No subject', $row['subject']);
        $this->assertSame('Unknown', $row['author']);
        $this->assertSame('', $row['body_preview']);
    }

    public function testSaveLastestMessagesDoesNotCountUpdatedMessageAsNew(): void
    {
        $this->repository->saveLastestMessages([
            ['id' => 'msg-1', 'subject' => 'Original', 'date' => '2026-06-01 10:00:00'],
        ]);

        $newCount = $this->repository->saveLastestMessages([
            ['id' => 'msg-1', 'subject' => 'Updated', 'date' => '2026-06-01 10:00:00'],
        ]);

        $this->assertSame(0, $newCount);

        $stmt = $this->pdo->query('SELECT subject FROM messages WHERE id = "msg-1"');
        $this->assertSame('Updated', $stmt->fetchColumn());
    }

    public function testSaveLastestMessagesKeepsOnlyTenMostRecentByDate(): void
    {
        $messages = [];
        for ($i = 1; $i <= 15; $i++) {
            $messages[] = [
                'id' => "msg-$i",
                'subject' => "Subject $i",
                'date' => sprintf('2026-06-%02d 10:00:00', $i),
            ];
        }

        $this->repository->saveLastestMessages($messages);

        $this->assertSame(10, (int) $this->pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn());

        $remainingIds = $this->pdo->query('SELECT id FROM messages ORDER BY date DESC')->fetchAll(PDO::FETCH_COLUMN);
        $expectedIds = array_map(fn (int $i) => "msg-$i", range(15, 6));

        $this->assertSame($expectedIds, $remainingIds);
    }

    public function testSaveLastestMessagesWithEmptyArrayReturnsZero(): void
    {
        $this->assertSame(0, $this->repository->saveLastestMessages([]));
    }
}
