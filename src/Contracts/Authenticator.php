<?php declare(strict_types=1);

namespace webnarmin\AmphpWS\Contracts;

use Amp\Http\Server\Request;

interface Authenticator
{
    public function authenticateControlHttp(Request $request): bool;
    public function authenticateWebSocket(Request $request): ?WebsocketUser;
}
