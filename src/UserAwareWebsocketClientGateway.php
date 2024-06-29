<?php declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\WebsocketClient;
use Amp\Future;
use webnarmin\AmphpWS\Contracts\WebsocketUser;

class UserAwareWebsocketClientGateway
{
    private WebsocketClientGateway $gateway;
    private array $clientUserMap = [];
    private array $clientMap = [];

    public function __construct(WebsocketClientGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function addClient(WebsocketClient $client, WebsocketUser $user): void
    {
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
        $clientIds = $this->getUserClientIds($userIds);
        return $this->gateway->multicastText($data, $clientIds);
    }

    public function multicastBinary(string $data, array $userIds): Future
    {
        $clientIds = $this->getUserClientIds($userIds);
        return $this->gateway->multicastBinary($data, $clientIds);
    }

    public function sendText(string $data, int $userId): Future
    {
        return $this->multicastText($data, [$userId]);
    }

    public function sendBinary(string $data, int $userId): Future
    {
        return $this->multicastBinary($data, [$userId]);
    }

    public function getClients(): array
    {
        return $this->gateway->getClients();
    }

    public function getOrignalGateway(): WebsocketClientGateway
    {
        return $this->gateway;
    }

    private function getUserClientIds(array $userIds): array
    {
        $clientIds = [];
        foreach ($userIds as $userId) {
            $clientIds = array_merge($clientIds, $this->getClientIdsByUserId($userId));
        }
        return $clientIds;
    }
}