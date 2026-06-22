<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Exception;

use RuntimeException;

class ControlHttpException extends RuntimeException implements AmphpWebSocketServerException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $statusCode = 400,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
