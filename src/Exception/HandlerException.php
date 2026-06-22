<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Exception;

use Throwable;

class HandlerException extends ProtocolException
{
    public function __construct(
        string $message,
        string $action,
        ?string $requestId = null,
        ?int $clientId = null,
        ?int $userId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            errorCode: 'handler_failed',
            message: $message,
            action: $action,
            clientId: $clientId,
            userId: $userId,
            requestId: $requestId,
            previous: $previous,
        );
    }
}
