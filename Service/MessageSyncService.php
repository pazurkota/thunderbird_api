<?php

namespace App\Services;

use App\Repositories\MessageRepositoryInterface;
use Exception;

class MessageSyncService
{
    private MessageRepositoryInterface $repository;
    private string $expectedToken;

    public function __construct(MessageRepositoryInterface $repository, string $expectedToken)
    {
        $this->repository = $repository;
        $this->expectedToken = $expectedToken;
    }

    public function handleAuthAndSync(?string $receivedToken, array $payload): array
    {
        if (!$receivedToken || $receivedToken !== $this->expectedToken) {
            throw new Exception("Unauthorized. Invalid API token.", 401);
        }

        if (!isset($payload['messages']) || !is_array($payload['messages'])) {
            throw new Exception("Invalid data format. Expected a 'messages' array.", 400);
        }

        $newSaved = $this->repository->saveLastestMessages($payload['messages']);

        return [
            'status' => 'success',
            'message' => 'Messages have been synchronized.',
            'new_messages_saved' => $newSaved,
            'server_time' => date('Y-m-d H:i:s')
        ];
    }
}