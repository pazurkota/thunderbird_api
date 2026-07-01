<?php

namespace App\Api;

use App\Database\Database;
use App\Repositories\SqliteAccountRepository;
use App\Repositories\SqliteMessageRepository;
use App\Services\AccountSyncService;
use App\Services\MessageSyncService;
use Exception;

class ThunderbirdApi
{
    private AccountSyncService $accountService;
    private MessageSyncService $messageService;

    public function __construct(AccountSyncService $accountService, MessageSyncService $messageService)
    {
        $this->accountService = $accountService;
        $this->messageService = $messageService;
    }

    public static function create(string $secretToken, string $sqliteDbPath): self
    {
        $pdo = Database::connect($sqliteDbPath);

        $accountService = new AccountSyncService(new SqliteAccountRepository($pdo), $secretToken);
        $messageService = new MessageSyncService(new SqliteMessageRepository($pdo), $secretToken);

        return new self($accountService, $messageService);
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
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        [$httpCode, $result] = $this->dispatch($token, $payload);

        http_response_code($httpCode);
        echo json_encode($result);
    }

    public function dispatch(?string $token, array $payload): array
    {
        try {
            if (isset($payload['messages'])) {
                $result = $this->messageService->handleAuthAndSync($token, $payload);
            } elseif (isset($payload['accounts'])) {
                $result = $this->accountService->handleAuthAndSync($token, $payload);
            } else {
                throw new Exception("Nie rozpoznano intencji żądania (brak klucza 'accounts' lub 'messages').", 400);
            }

            return [200, $result];
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
