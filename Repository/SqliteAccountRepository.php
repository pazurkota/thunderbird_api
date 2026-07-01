<?php

namespace App\Repositories;

use PDO;

class SqliteAccountRepository implements AccountRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function syncAccounts(array $accounts): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO accounts (tb_account_id, account_name, identities, last_sync, status)
            VALUES (:tb_account_id, :account_name, :identities, :last_sync, "active")
            ON CONFLICT(tb_account_id) DO UPDATE SET
                account_name = excluded.account_name,
                identities   = excluded.identities,
                last_sync    = excluded.last_sync,
                status       = excluded.status
        ');

        $syncedCount = 0;
        $currentTimestamp = date('Y-m-d H:i:s');

        foreach ($accounts as $account) {
            if (!isset($account['tb_account_id'])) {
                continue;
            }

            $stmt->execute([
                ':tb_account_id' => $account['tb_account_id'],
                ':account_name'  => $account['account_name'] ?? 'undefined',
                ':identities'    => json_encode($account['identities'] ?? []),
                ':last_sync'     => $currentTimestamp,
            ]);
            $syncedCount++;
        }

        return $syncedCount;
    }
}
