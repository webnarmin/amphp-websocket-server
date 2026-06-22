<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Exception;

class AuthenticationException extends ProtocolException
{
    public function __construct(?int $clientId = null)
    {
        parent::__construct(
            errorCode: 'authentication_failed',
            message: 'WebSocket authentication failed.',
            clientId: $clientId,
        );
    }
}
