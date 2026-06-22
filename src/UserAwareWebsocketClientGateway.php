<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Amp\Future;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\WebsocketClient;
use webnarmin\AmphpWS\Contracts\WebsocketUser;
use webnarmin\AmphpWS\Exception\GatewayException;

class UserAwareWebsocketClientGateway
{
    private WebsocketGateway $gateway;
    /** @var array<int, int> */
    private array $clientUserMap = [];
    /** @var array<int, WebsocketUser> */
    private array $clientMap = [];

    public function __construct(?WebsocketGateway $gateway = null)
    {
        $this->gateway = $gateway ?? new WebsocketClientGateway();
    }

    public function addClient(WebsocketClient $client, WebsocketUser $user): void
    {
        $this->assertValidUserId($user->getId());

        $this->gateway->addClient($client);
        $this->clientUserMap[$client->getId()] = $user->getId();
        $this->clientMap[$client->getId()] = $user;

        $client->onClose(function () use ($client) {
            $clientId = $client->getId();
            unset($this->clientUserMap[$clientId], $this->clientMap[$clientId]);
        });
    }

    public function getUserIdByClientId(int $clientId): ?int
    {
        return $this->clientUserMap[$clientId] ?? null;
    }

    public function getUserByClientId(int $clientId): ?WebsocketUser
    {
        return $this->clientMap[$clientId] ?? null;
    }

    public function getClientIdsByUserId(int $userId): array
    {
        $this->assertValidUserId($userId);

        return array_keys($this->clientUserMap, $userId, true);
    }

    public function broadcastText(string $data, array $excludedUserIds = []): Future
    {
        $excludedClientIds = $this->getUserClientIds($excludedUserIds);
        return $this->gateway->broadcastText($data, $excludedClientIds);
    }

    public function broadcastBinary(string $data, array $excludedUserIds = []): Future
    {
        $excludedClientIds = $this->getUserClientIds($excludedUserIds);
        return $this->gateway->broadcastBinary($data, $excludedClientIds);
    }

    public function multicastText(string $data, array $userIds): Future
    {
        $this->assertValidUserIds($userIds);

        $clientIds = $this->getUserClientIds($userIds);

        return $this->gateway->multicastText($data, $clientIds);
    }

    public function multicastBinary(string $data, array $userIds): Future
    {
        $this->assertValidUserIds($userIds);

        $clientIds = $this->getUserClientIds($userIds);

        return $this->gateway->multicastBinary($data, $clientIds);
    }

    public function sendText(int $userId, string $data): Future
    {
        return $this->multicastText($data, [$userId]);
    }

    public function sendBinary(int $userId, string $data): Future
    {
        return $this->multicastBinary($data, [$userId]);
    }

    public function getClients(): array
    {
        return $this->gateway->getClients();
    }

    public function getOriginalGateway(): WebsocketGateway
    {
        return $this->gateway;
    }

    private function getUserClientIds(array $userIds): array
    {
        $this->assertValidUserIds($userIds);

        $clientIds = [];
        foreach ($userIds as $userId) {
            $clientIds = array_merge($clientIds, $this->getClientIdsByUserId($userId));
        }

        return $clientIds;
    }

    /**
     * @param array<int, mixed> $userIds
     */
    private function assertValidUserIds(array $userIds): void
    {
        foreach ($userIds as $userId) {
            if (!is_int($userId) || $userId <= 0) {
                throw new GatewayException('User ids must be positive integers.');
            }
        }
    }

    private function assertValidUserId(int $userId): void
    {
        if ($userId <= 0) {
            throw new GatewayException('User id must be a positive integer.');
        }
    }
}
