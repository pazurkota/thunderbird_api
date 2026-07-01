<?php

namespace App\Tests\Repository;

use App\Database\Database;
use App\Repositories\SqliteAccountRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class SqliteAccountRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SqliteAccountRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = Database::connect(':memory:');
        $this->repository = new SqliteAccountRepository($this->pdo);
    }

    public function testSyncAccountsInsertsNewAccounts(): void
    {
        $count = $this->repository->syncAccounts([
            ['tb_account_id' => 'acc-1', 'account_name' => 'Personal', 'identities' => ['a@example.com']],
            ['tb_account_id' => 'acc-2', 'account_name' => 'Work', 'identities' => ['b@example.com']],
        ]);

        $this->assertSame(2, $count);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn());
    }

    public function testSyncAccountsSkipsEntriesMissingTbAccountId(): void
    {
        $count = $this->repository->syncAccounts([
            ['account_name' => 'No id'],
            ['tb_account_id' => 'acc-1', 'account_name' => 'Personal'],
        ]);

        $this->assertSame(1, $count);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn());
    }

    public function testSyncAccountsAppliesDefaultsForMissingFields(): void
    {
        $this->repository->syncAccounts([
            ['tb_account_id' => 'acc-1'],
        ]);

        $stmt = $this->pdo->query('SELECT account_name, identities, status FROM accounts WHERE tb_account_id = "acc-1"');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('undefined', $row['account_name']);
        $this->assertSame('[]', $row['identities']);
        $this->assertSame('active', $row['status']);
    }

    public function testSyncAccountsUpsertsExistingAccount(): void
    {
        $this->repository->syncAccounts([
            ['tb_account_id' => 'acc-1', 'account_name' => 'Old Name', 'identities' => ['old@example.com']],
        ]);

        $this->repository->syncAccounts([
            ['tb_account_id' => 'acc-1', 'account_name' => 'New Name', 'identities' => ['new@example.com']],
        ]);

        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn());

        $stmt = $this->pdo->query('SELECT account_name, identities FROM accounts WHERE tb_account_id = "acc-1"');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('New Name', $row['account_name']);
        $this->assertSame(json_encode(['new@example.com']), $row['identities']);
    }

    public function testSyncAccountsWithEmptyArrayReturnsZero(): void
    {
        $this->assertSame(0, $this->repository->syncAccounts([]));
    }
}
