<?php

namespace App\Tests\Service;

use App\Services\EnvService;
use App\Services\TokenResetService;
use Exception;
use PHPUnit\Framework\TestCase;

class TokenResetServiceTest extends TestCase
{
    private const TOKEN = 'secret-token';

    public function testThrowsUnauthorizedWhenTokenIsMissing(): void
    {
        $envService = $this->createMock(EnvService::class);
        $envService->expects($this->never())->method('updateKey');

        $service = new TokenResetService($envService, self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unauthorized. Invalid API token.');
        $this->expectExceptionCode(401);

        $service->handleAuthAndReset(null);
    }

    public function testThrowsUnauthorizedWhenTokenIsInvalid(): void
    {
        $envService = $this->createMock(EnvService::class);
        $envService->expects($this->never())->method('updateKey');

        $service = new TokenResetService($envService, self::TOKEN);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);

        $service->handleAuthAndReset('wrong-token');
    }

    public function testReturnsSuccessResultAndPersistsNewTokenOnValidRequest(): void
    {
        $envService = $this->createMock(EnvService::class);
        $envService->expects($this->once())
            ->method('updateKey')
            ->with(
                'THUNDERBIRD_API_TOKEN',
                $this->matchesRegularExpression('/^[a-f0-9]{32}$/')
            );

        $service = new TokenResetService($envService, self::TOKEN);
        $result = $service->handleAuthAndReset(self::TOKEN);

        $this->assertSame('success', $result['status']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result['new_token']);
        $this->assertNotSame(self::TOKEN, $result['new_token']);
        $this->assertArrayHasKey('server_time', $result);
    }

    public function testGeneratesDifferentTokenOnEachReset(): void
    {
        $envService = $this->createMock(EnvService::class);

        $service = new TokenResetService($envService, self::TOKEN);

        $first = $service->handleAuthAndReset(self::TOKEN);
        $second = $service->handleAuthAndReset(self::TOKEN);

        $this->assertNotSame($first['new_token'], $second['new_token']);
    }
}
