<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Api\GmailApi;
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

$dbPath = $_ENV['GMAIL_DB_PATH'] ?? (__DIR__ . '/Database/gmail.sqlite');

GmailApi::create($secretToken, $dbPath)->run();