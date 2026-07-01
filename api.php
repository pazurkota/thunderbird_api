<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Api\ThunderbirdApi;

$secretToken = 'pazurkota_super_secret_api_key_2026';
$sqliteDbPath = __DIR__ . '/Database/thunderbird.sqlite';

ThunderbirdApi::create($secretToken, $sqliteDbPath)->run();
