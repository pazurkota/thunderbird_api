<?php

namespace App\Tests\Service;

use App\Repositories\MessageRepositoryInterface;
use App\Services\MessageSyncService;
use Exception;
use PHPUnit\Framework\TestCase;

class MessageSyncServiceTest extends TestCase
{
    private const TOKEN = 'secret-token';

    public function testThrowsUnauthorizedWhenTokenIsMissing(): void
    {
        $service = new MessageSyncService($this->createMock(MessageRepositoryInterface::class), self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Brak autoryzacji. Niepoprawny token API.');
        $this->expectExceptionCode(401);

        $service->handleAuthAndSync(null, ['messages' => []]);
    }

    public function testThrowsUnauthorizedWhenTokenIsInvalid(): void
    {
        $service = new MessageSyncService($this->createMock(MessageRepositoryInterface::class), self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);

        $service->handleAuthAndSync('wrong-token', ['messages' => []]);
    }

    public function testThrowsBadRequestWhenMessagesKeyIsMissing(): void
    {
        $service = new MessageSyncService($this->createMock(MessageRepositoryInterface::class), self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Zły format danych. Oczekiwano tablicy 'messages'.");
        $this->expectExceptionCode(400);

        $service->handleAuthAndSync(self::TOKEN, []);
    }

    public function testThrowsBadRequestWhenMessagesIsNotAnArray(): void
    {
        $service = new MessageSyncService($this->createMock(MessageRepositoryInterface::class), self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $service->handleAuthAndSync(self::TOKEN, ['messages' => 'not-an-array']);
    }

    public function testReturnsSuccessResultOnValidRequest(): void
    {
        $messages = [['id' => 'msg-1']];

        $repository = $this->createMock(MessageRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('saveLastestMessages')
            ->with($messages)
            ->willReturn(1);

        $service = new MessageSyncService($repository, self::TOKEN);
        $result = $service->handleAuthAndSync(self::TOKEN, ['messages' => $messages]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['new_messages_saved']);
        $this->assertArrayHasKey('server_time', $result);
    }
}
