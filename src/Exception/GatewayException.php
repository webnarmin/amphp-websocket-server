<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Exception;

use RuntimeException;

class GatewayException extends RuntimeException implements AmphpWebSocketServerException
{
}
