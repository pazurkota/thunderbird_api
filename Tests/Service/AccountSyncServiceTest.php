<?php

namespace App\Tests\Service;

use App\Repositories\AccountRepositoryInterface;
use App\Services\AccountSyncService;
use Exception;
use PHPUnit\Framework\TestCase;

class AccountSyncServiceTest extends TestCase
{
    private const TOKEN = 'secret-token';

    public function testThrowsUnauthorizedWhenTokenIsMissing(): void
    {
        $service = new AccountSyncService($this->createMock(AccountRepositoryInterface::class), self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Brak autoryzacji. Niepoprawny token API.');
        $this->expectExceptionCode(401);

        $service->handleAuthAndSync(null, ['accounts' => []]);
    }

    public function testThrowsUnauthorizedWhenTokenIsInvalid(): void
    {
        $service = new AccountSyncService($this->createMock(AccountRepositoryInterface::class), self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);

        $service->handleAuthAndSync('wrong-token', ['accounts' => []]);
    }

    public function testThrowsBadRequestWhenAccountsKeyIsMissing(): void
    {
        $service = new AccountSyncService($this->createMock(AccountRepositoryInterface::class), self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Zły format danych wejściowych.');
        $this->expectExceptionCode(400);

        $service->handleAuthAndSync(self::TOKEN, []);
    }

    public function testThrowsBadRequestWhenAccountsIsNotAnArray(): void
    {
        $service = new AccountSyncService($this->createMock(AccountRepositoryInterface::class), self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $service->handleAuthAndSync(self::TOKEN, ['accounts' => 'not-an-array']);
    }

    public function testReturnsSuccessResultOnValidRequest(): void
    {
        $accounts = [['tb_account_id' => 'acc-1']];

        $repository = $this->createMock(AccountRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('syncAccounts')
            ->with($accounts)
            ->willReturn(1);

        $service = new AccountSyncService($repository, self::TOKEN);
        $result = $service->handleAuthAndSync(self::TOKEN, ['accounts' => $accounts]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['synchronized_accounts']);
        $this->assertArrayHasKey('server_time', $result);
    }
}
