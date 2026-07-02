<?php

namespace App\Services;

use Exception;

class TokenResetService
{
    private EnvService $envService;
    private string $expectedToken;

    public function __construct(EnvService $envService, string $expectedToken)
    {
        $this->envService = $envService;
        $this->expectedToken = $expectedToken;
    }

    public function handleAuthAndReset(?string $receivedToken): array
    {
        if (!$receivedToken || $receivedToken !== $this->expectedToken) {
            throw new Exception("Unauthorized. Invalid API token.", 401);
        }

        $newToken = bin2hex(random_bytes(16));
        $this->envService->updateKey('THUNDERBIRD_API_TOKEN', $newToken);

        return [
            'status' => 'success',
            'message' => 'Auth token has been reset.',
            'new_token' => $newToken,
            'server_time' => date('Y-m-d H:i:s')
        ];
    }
}
