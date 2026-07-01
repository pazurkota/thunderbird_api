<?php

namespace App\Services;

use App\Repositories\AccountRepositoryInterface;
use Exception;

class AccountSyncService
{
    private AccountRepositoryInterface $repository;
    private string $expectedToken;

    public function __construct(AccountRepositoryInterface $repository, string $expectedToken)
    {
        $this->repository = $repository;
        $this->expectedToken = $expectedToken;
    }

    public function handleAuthAndSync(?string $receivedToken, array $payload): array
    {
        if (!$receivedToken || $receivedToken !== $this->expectedToken) {
            throw new Exception("Brak autoryzacji. Niepoprawny token API.", 401);
        }

        if (!isset($payload['accounts']) || !is_array($payload['accounts'])) {
            throw new Exception("Zły format danych wejściowych.", 400);
        }

        $count = $this->repository->syncAccounts($payload['accounts']);

        return [
            'status' => 'success',
            'synchronized_accounts' => $count,
            'server_time' => date('Y-m-d H:i:s')
        ];
    }
}