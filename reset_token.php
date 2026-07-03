<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Api\ResetTokenApi;
use App\Service\TokenService;
use App\Service\TokenResetService;
use App\Service\EnvService;
use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->load();
}

$secretToken = $_ENV['GMAIL_API_TOKEN'] ?? getenv('GMAIL_API_TOKEN');

if (!$secretToken) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server misconfiguration: GMAIL_API_TOKEN is not set.']);
    exit;
}

$envService = new EnvService(__DIR__ . '/.env');
$tokenService = new TokenService($secretToken);
$resetService = new TokenResetService($envService);

(new ResetTokenApi($tokenService, $resetService))->run();