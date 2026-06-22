<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Exception;

use RuntimeException;
use Throwable;

class ProtocolException extends RuntimeException implements AmphpWebSocketServerException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly ?string $action = null,
        private readonly ?int $clientId = null,
        private readonly ?int $userId = null,
        private readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
