<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use webnarmin\AmphpWS\Contracts\WebsocketUser;
use webnarmin\AmphpWS\Exception\HandlerException;
use webnarmin\AmphpWS\Exception\ProtocolException;
use webnarmin\AmphpWS\Protocol\MessageEnvelope;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;

final class MessageProcessor
{
    public function __construct(
        private readonly ActionRouter $router,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ServerHooks $hooks = new ServerHooks(),
    ) {
    }

    public function process(WebsocketUser $user, string $rawMessage, int $clientId): ?ResponseEnvelope
    {
        try {
            $message = MessageEnvelope::fromJson($rawMessage);
        } catch (ProtocolException $exception) {
            $this->hooks->onInvalidMessage($user, $clientId, $exception, $rawMessage);

            return ResponseEnvelope::error($exception->getErrorCode(), $exception->getMessage(), $exception->getRequestId());
        }

        try {
            $response = $this->router->dispatch($user, $message);
        } catch (HandlerException $exception) {
            $this->logger->error('WebSocket message handler failed', [
                'action' => $exception->getAction(),
                'request_id' => $exception->getRequestId(),
                'client_id' => $clientId,
                'user_id' => $user->getId(),
                'message' => $exception->getMessage(),
            ]);
            $this->hooks->onUnhandledException($user, $clientId, $exception);

            return ResponseEnvelope::error($exception->getErrorCode(), 'Message handler failed.', $exception->getRequestId());
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected WebSocket message failure', [
                'client_id' => $clientId,
                'user_id' => $user->getId(),
                'message' => $exception->getMessage(),
            ]);
            $this->hooks->onUnhandledException($user, $clientId, $exception);

            return ResponseEnvelope::error('handler_failed', 'Message handler failed.', $message->getRequestId());
        }

        if ($response !== null && $response->getRequestId() === null) {
            return $response->withRequestId($message->getRequestId());
        }

        return $response;
    }
}
