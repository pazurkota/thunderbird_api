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

    /**
     * Waliduje token i uruchamia proces synchronizacji.
     */
    public function handleAuthAndSync(?string $receivedToken, array $payload): array
    {
        // Walidacja autoryzacji
        if (!$receivedToken || $receivedToken !== $this->expectedToken) {
            throw new Exception("Brak autoryzacji. Niepoprawny token API.", 401);
        }

        // Walidacja struktury danych
        if (!isset($payload['accounts']) || !is_array($payload['accounts'])) {
            throw new Exception("Zły format danych wejściowych.", 400);
        }

        // Delegacja zapisu do repozytorium
        $count = $this->repository->syncAccounts($payload['accounts']);

        return [
            'status' => 'success',
            'synchronized_accounts' => $count,
            'server_time' => date('Y-m-d H:i:s')
        ];
    }
}