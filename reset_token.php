<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Api\ResetTokenApi;
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

ResetTokenApi::create($secretToken, __DIR__ . '/.env')->run();
