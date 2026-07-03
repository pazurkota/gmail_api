<?php

namespace App\Api;

use App\Service\TokenService;
use App\Service\TokenResetService;

class ResetTokenApi
{
    public function __construct(
        private TokenService $tokenService,
        private TokenResetService $resetService
    ) {}

    public function run(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $providedToken = $_SERVER['HTTP_X_GMAIL_TOKEN'] ?? '';
        if (!$this->tokenService->isValid($providedToken)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $newToken = $this->resetService->reset();

        echo json_encode([
            'status' => 'success',
            'message' => 'Auth token has been reset.',
            'new_token' => $newToken,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }
}