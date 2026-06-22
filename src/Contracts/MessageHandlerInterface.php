<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Contracts;

use webnarmin\AmphpWS\Protocol\MessageEnvelope;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;

interface MessageHandlerInterface
{
    public function handle(WebsocketUser $user, MessageEnvelope $message): ?ResponseEnvelope;
}
