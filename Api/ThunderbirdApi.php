<?php

// Ładowanie komponentów dla bazy danych
require_once __DIR__ . '/../Database/Database.php';

// Ładowanie komponentów dla Kont
require_once __DIR__ . '/../Interface/AccountRepositoryInterface.php';
require_once __DIR__ . '/../Repository/SqliteAccountRepository.php';
require_once __DIR__ . '/../Service/AccountSyncService.php';

// Ładowanie komponentów dla Wiadomości
require_once __DIR__ . '/../Interface/MessageRepositoryInterface.php';
require_once __DIR__ . '/../Repository/SqliteMessageRepository.php';
require_once __DIR__ . '/../Service/MessageSyncService.php';

use App\Database\Database;
use App\Repositories\SqliteAccountRepository;
use App\Repositories\SqliteMessageRepository;
use App\Services\AccountSyncService;
use App\Services\MessageSyncService;

// Ogólna konfiguracja systemu
$secretToken = 'pazurkota_super_secret_api_key_2026';
$sqliteDbPath = __DIR__ . '/../Database/thunderbird.sqlite';

// Inicjalizacja usług
$pdo = Database::connect($sqliteDbPath);

$accountRepo = new SqliteAccountRepository($pdo);
$accountService = new AccountSyncService($accountRepo, $secretToken);

$messageRepo = new SqliteMessageRepository($pdo);
$messageService = new MessageSyncService($messageRepo, $secretToken);

// ==========================================
// Obsługa protokołu HTTP
// ==========================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Thunderbird-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(200); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$headers = getallheaders();
$token = $headers['X-Thunderbird-Token'] ?? null;
$payload = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    // Prosty routing oparty o strukturę dokumentu JSON
    if (isset($payload['messages'])) {
        // Jeśli payload zawiera klucz 'messages', kierujemy ruch do serwisu wiadomości
        $result = $messageService->handleAuthAndSync($token, $payload);
    } elseif (isset($payload['accounts'])) {
        // Jeśli zawiera 'accounts', obsługujemy konta
        $result = $accountService->handleAuthAndSync($token, $payload);
    } else {
        throw new Exception("Nie rozpoznano intencji żądania (brak klucza 'accounts' lub 'messages').", 400);
    }

    http_response_code(200);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode(['error' => $e->getMessage()]);
}