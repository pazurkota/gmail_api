<?php

namespace App\Api;

use App\Database\Database;
use App\Repository\SqliteUserRepository;
use App\Repository\SqliteMessageRepository;
use App\Service\TokenService;
use App\Service\UserSyncService;
use App\Service\MessageSyncService;

class GmailApi
{
    private function __construct(
        private TokenService $tokenService,
        private UserSyncService $userSyncService,
        private MessageSyncService $messageSyncService
    ) {}

    public static function create(string $secretToken, string $dbPath): self
    {
        $db = new Database($dbPath);
        return new self(
            new TokenService($secretToken),
            new UserSyncService(new SqliteUserRepository($db)),
            new MessageSyncService(new SqliteMessageRepository($db))
        );
    }

    public function run(): void
    {
        header('Content-Type: application/json');
        $this->handleCors();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(405, ['error' => 'Method not allowed']);
        }

        $providedToken = $_SERVER['HTTP_X_GMAIL_TOKEN'] ?? '';
        if (!$this->tokenService->isValid($providedToken)) {
            $this->respond(401, ['error' => 'Unauthorized']);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $this->respond(400, ['error' => 'Nieprawidłowy JSON']);
        }

        try {
            if (isset($payload['user'])) {
                $this->userSyncService->sync($payload['user']);
                $this->respond(200, ['status' => 'success', 'type' => 'user']);
            }

            if (isset($payload['messages'], $payload['userEmail'])) {
                $saved = $this->messageSyncService->sync($payload['messages'], $payload['userEmail']);
                $this->respond(200, ['status' => 'success', 'type' => 'messages', 'saved' => $saved]);
            }

            $this->respond(400, ['error' => 'Nierozpoznany kształt payloadu']);
        } catch (\InvalidArgumentException $e) {
            $this->respond(400, ['error' => $e->getMessage()]);
        }
    }

    private function handleCors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, X-Gmail-Token');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    private function respond(int $code, array $body): never
    {
        http_response_code($code);
        echo json_encode($body);
        exit;
    }
}