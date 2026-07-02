<?php

namespace App\Api;

use App\Services\EnvService;
use App\Services\TokenResetService;
use Exception;

class ResetTokenApi
{
    private TokenResetService $tokenResetService;

    public function __construct(TokenResetService $tokenResetService)
    {
        $this->tokenResetService = $tokenResetService;
    }

    public static function create(string $secretToken, string $envPath): self
    {
        $tokenResetService = new TokenResetService(new EnvService($envPath), $secretToken);

        return new self($tokenResetService);
    }

    public function run(): void
    {
        $this->sendCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $headers = getallheaders();
        $token = $headers['X-Thunderbird-Token'] ?? null;

        [$httpCode, $result] = $this->dispatch($token);

        http_response_code($httpCode);
        echo json_encode($result);
    }

    public function dispatch(?string $token): array
    {
        try {
            return [200, $this->tokenResetService->handleAuthAndReset($token)];
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 ? $e->getCode() : 500;
            return [$code, ['error' => $e->getMessage()]];
        }
    }

    private function sendCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Thunderbird-Token');
        header('Content-Type: application/json');
    }
}
