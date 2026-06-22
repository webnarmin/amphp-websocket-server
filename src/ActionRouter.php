<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Throwable;
use webnarmin\AmphpWS\Contracts\MessageHandlerInterface;
use webnarmin\AmphpWS\Contracts\WebsocketUser;
use webnarmin\AmphpWS\Exception\HandlerException;
use webnarmin\AmphpWS\Protocol\MessageEnvelope;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;

final class ActionRouter
{
    /** @var array<string, callable|MessageHandlerInterface> */
    private array $handlers = [];

    public function on(string $action, callable|MessageHandlerInterface $handler): self
    {
        $action = trim($action);
        if ($action === '') {
            throw new \InvalidArgumentException('Action name must be a non-empty string.');
        }

        $this->handlers[$action] = $handler;

        return $this;
    }

    public function dispatch(WebsocketUser $user, MessageEnvelope $message): ?ResponseEnvelope
    {
        $handler = $this->handlers[$message->getAction()] ?? null;
        if ($handler === null) {
            return ResponseEnvelope::error(
                'unsupported_action',
                sprintf("Action '%s' is not supported.", $message->getAction()),
                $message->getRequestId(),
            );
        }

        try {
            $response = $handler instanceof MessageHandlerInterface
                ? $handler->handle($user, $message)
                : $handler($user, $message);
        } catch (Throwable $exception) {
            throw new HandlerException(
                message: $exception->getMessage(),
                action: $message->getAction(),
                requestId: $message->getRequestId(),
                userId: $user->getId(),
                previous: $exception,
            );
        }

        if ($response !== null && !$response instanceof ResponseEnvelope) {
            throw new HandlerException(
                message: 'Message handler must return ResponseEnvelope or null.',
                action: $message->getAction(),
                requestId: $message->getRequestId(),
                userId: $user->getId(),
            );
        }

        return $response;
    }

    public function has(string $action): bool
    {
        return isset($this->handlers[$action]);
    }
}
