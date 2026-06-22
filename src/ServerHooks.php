<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Amp\Websocket\WebsocketClient;
use Throwable;
use webnarmin\AmphpWS\Contracts\WebsocketUser;
use webnarmin\AmphpWS\Exception\ProtocolException;

final class ServerHooks
{
    /**
     * @param null|callable(WebsocketUser, WebsocketClient): void $authenticated
     * @param null|callable(WebsocketUser, int): void $disconnected
     * @param null|callable(?WebsocketUser, int, ProtocolException, string): void $invalidMessage
     * @param null|callable(?WebsocketUser, int, Throwable): void $unhandledException
     */
    public function __construct(
        private readonly mixed $authenticated = null,
        private readonly mixed $disconnected = null,
        private readonly mixed $invalidMessage = null,
        private readonly mixed $unhandledException = null,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    public function onAuthenticated(WebsocketUser $user, WebsocketClient $client): void
    {
        if (is_callable($this->authenticated)) {
            ($this->authenticated)($user, $client);
        }
    }

    public function onDisconnected(WebsocketUser $user, int $clientId): void
    {
        if (is_callable($this->disconnected)) {
            ($this->disconnected)($user, $clientId);
        }
    }

    public function onInvalidMessage(?WebsocketUser $user, int $clientId, ProtocolException $exception, string $rawMessage): void
    {
        if (is_callable($this->invalidMessage)) {
            ($this->invalidMessage)($user, $clientId, $exception, $rawMessage);
        }
    }

    public function onUnhandledException(?WebsocketUser $user, int $clientId, Throwable $exception): void
    {
        if (is_callable($this->unhandledException)) {
            ($this->unhandledException)($user, $clientId, $exception);
        }
    }
}
