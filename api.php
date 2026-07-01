<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Api\ThunderbirdApi;
use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->load();
}

$secretToken = $_ENV['THUNDERBIRD_API_TOKEN'] ?? getenv('THUNDERBIRD_API_TOKEN');

if (!$secretToken) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server misconfiguration: THUNDERBIRD_API_TOKEN is not set. Copy .env.example to .env and set it.']);
    exit;
}

$sqliteDbPath = $_ENV['THUNDERBIRD_DB_PATH'] ?? (__DIR__ . '/Database/thunderbird.sqlite');

ThunderbirdApi::create($secretToken, $sqliteDbPath)->run();
