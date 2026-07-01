<?php

namespace App\Repositories;

interface AccountRepositoryInterface
{
    public function syncAccounts(array $accounts): int;
}