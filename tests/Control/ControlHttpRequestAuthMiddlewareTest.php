<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Control;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Psr\Log\NullLogger;
use webnarmin\AmphpWS\Contracts\Authenticator;
use webnarmin\AmphpWS\ControlHttpRequestAuthMiddleware;

final class ControlHttpRequestAuthMiddlewareTest extends TestCase
{
    public function test_post_request_with_invalid_token_returns_json_unauthorized(): void
    {
        $authenticator = $this->createMock(Authenticator::class);
        $authenticator->method('authenticateControlHttp')->willReturn(false);
        $middleware = new ControlHttpRequestAuthMiddleware($authenticator, new NullLogger());
        $request = new Request($this->createMock(Client::class), 'POST', $this->createMock(UriInterface::class));
        $next = $this->createMock(RequestHandler::class);

        $response = $middleware->handleRequest($request, $next);

        self::assertSame(401, $response->getStatus());
        self::assertStringContainsString('authentication_failed', (string) $response->getBody()->read());
    }

    public function test_valid_post_request_is_delegated(): void
    {
        $authenticator = $this->createMock(Authenticator::class);
        $authenticator->method('authenticateControlHttp')->willReturn(true);
        $middleware = new ControlHttpRequestAuthMiddleware($authenticator, new NullLogger());
        $request = new Request($this->createMock(Client::class), 'POST', $this->createMock(UriInterface::class));
        $next = $this->createMock(RequestHandler::class);
        $next->expects(self::once())->method('handleRequest')->willReturn(new Response(200, [], 'OK'));

        self::assertSame(200, $middleware->handleRequest($request, $next)->getStatus());
    }
}
