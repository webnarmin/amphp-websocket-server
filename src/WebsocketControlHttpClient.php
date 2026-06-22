<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use webnarmin\AmphpWS\Control\ControlResult;

class WebsocketControlHttpClient
{
    private ClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(ClientInterface $client, ?LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger ?? new NullLogger();
    }

    public function sendText(int $userId, string $payload): ControlResult
    {
        return $this->sendRequest('POST', '/send-text', [
            'userId' => $userId,
            'payload' => $payload,
        ]);
    }

    public function broadcastText(string $payload, array $excludedUserIds = []): ControlResult
    {
        return $this->sendRequest('POST', '/broadcast-text', [
            'payload' => $payload,
            'excludedUserIds' => $excludedUserIds,
        ]);
    }

    public function broadcastBinary(string $payload, array $excludedUserIds = []): ControlResult
    {
        return $this->sendRequest('POST', '/broadcast-binary', [
            'payload' => base64_encode($payload),
            'excludedUserIds' => $excludedUserIds,
        ]);
    }

    public function multicastText(string $payload, array $userIds): ControlResult
    {
        return $this->sendRequest('POST', '/multicast-text', [
            'payload' => $payload,
            'userIds' => $userIds,
        ]);
    }

    public function multicastBinary(string $payload, array $userIds): ControlResult
    {
        return $this->sendRequest('POST', '/multicast-binary', [
            'payload' => base64_encode($payload),
            'userIds' => $userIds,
        ]);
    }

    private function sendRequest(string $method, string $endpoint, array $data): ControlResult
    {
        $this->logger->info('Attempting to send request', ['method' => $method, 'endpoint' => $endpoint]);
        try {
            $response = $this->client->request($method, $endpoint, [
                'json' => $data,
            ]);
            $result = ControlResult::fromResponse($response);
            $this->logger->info('Request completed', ['statusCode' => $result->getStatusCode()]);

            return $result;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to send request', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return ControlResult::failure(null, 'request_failed', $e->getMessage());
        }
    }
}
