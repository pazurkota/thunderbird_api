<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Service/EnvService.php';

use App\Services\EnvService;

if (php_sapi_name() !== 'cli') {
    die("This script can be used only in terminal.\n");
}

$envPath = __DIR__ . '/.env';
$envManager = new EnvService($envPath);

$newToken = bin2hex(random_bytes(16));

try {
    $envManager->updateKey('THUNDERBIRD_API_TOKEN', $newToken);

    echo "==========================================================\n";
    echo " SUCCESS: Auth token has been successfully reset!\n";
    echo "================================================
    ==========\n";
    echo "New token: \033[1;32m" . $newToken . "\033[0m\n";
    echo "New token has been saved into your .env file.\n";
    echo "Remember to update it in your Thunderbird plugin!\n";
    echo "==========================================================\n";

} catch (Exception $e) {
    echo "Error occurred while reset token: " . $e->getMessage() . "\n";
}
