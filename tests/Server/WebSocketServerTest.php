<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Server;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\WebsocketClient;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Psr\Log\NullLogger;
use webnarmin\AmphpWS\ActionRouter;
use webnarmin\AmphpWS\Configurator;
use webnarmin\AmphpWS\Contracts\Authenticator;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;
use webnarmin\AmphpWS\ServerHooks;
use webnarmin\AmphpWS\WebSocketServer;
use webnarmin\AmphpWSTest\Fakes\FakeWebsocketUser;

final class WebSocketServerTest extends TestCase
{
    public function test_process_raw_message_uses_router(): void
    {
        $router = new ActionRouter();
        $router->on('ping', static fn (): ResponseEnvelope => ResponseEnvelope::success(['pong' => true]));

        $server = $this->createServer($router);
        $response = $server->processRawMessage(
            new FakeWebsocketUser(11),
            '{"action":"ping","payload":{},"requestId":"req-1"}',
            22
        );

        self::assertNotNull($response);
        self::assertSame(['pong' => true], $response->getPayload());
        self::assertSame('req-1', $response->getRequestId());
    }

    public function test_auth_failure_closes_client_with_policy_violation(): void
    {
        $authenticator = $this->createMock(Authenticator::class);
        $authenticator->method('authenticateWebSocket')->willReturn(null);
        $authenticator->method('authenticateControlHttp')->willReturn(true);
        $client = $this->createMock(WebsocketClient::class);
        $client->method('getId')->willReturn(99);
        $client->expects(self::once())->method('close')->with(1008, 'Authentication failed');

        $server = new WebSocketServer(
            new Configurator(),
            $authenticator,
            new ActionRouter(),
            new NullLogger(),
        );

        $server->handleClient(
            $client,
            new Request($this->createMock(Client::class), 'GET', $this->createMock(UriInterface::class)),
            new Response(),
        );
    }

    public function test_authenticated_client_is_added_and_hooks_are_called(): void
    {
        $trace = new ArrayObject();
        $user = new FakeWebsocketUser(55);
        $authenticator = $this->createMock(Authenticator::class);
        $authenticator->method('authenticateWebSocket')->willReturn($user);
        $authenticator->method('authenticateControlHttp')->willReturn(true);
        $client = $this->createMock(WebsocketClient::class);
        $closeCallbacks = [];

        $client->method('getId')->willReturn(44);
        $client->method('receive')->willReturn(null);
        $client->method('onClose')->willReturnCallback(static function ($callback) use (&$closeCallbacks): void {
            $closeCallbacks[] = $callback;
        });

        $server = new WebSocketServer(
            new Configurator(),
            $authenticator,
            new ActionRouter(),
            new NullLogger(),
            new ServerHooks(
                authenticated: static function ($hookUser) use ($trace): void {
                    $trace->append(['authenticated', $hookUser->getId()]);
                },
                disconnected: static function ($hookUser, $clientId) use ($trace): void {
                    $trace->append(['disconnected', $hookUser->getId(), $clientId]);
                },
            ),
        );

        $server->handleClient(
            $client,
            new Request($this->createMock(Client::class), 'GET', $this->createMock(UriInterface::class)),
            new Response(),
        );

        self::assertSame(55, $server->getGateway()->getUserIdByClientId(44));
        self::assertSame([['authenticated', 55]], $trace->getArrayCopy());
        self::assertNotEmpty($closeCallbacks);

        foreach ($closeCallbacks as $callback) {
            $callback();
        }

        self::assertNull($server->getGateway()->getUserIdByClientId(44));
        self::assertContains(['disconnected', 55, 44], $trace->getArrayCopy());
    }

    private function createServer(ActionRouter $router): WebSocketServer
    {
        $authenticator = $this->createMock(Authenticator::class);
        $authenticator->method('authenticateControlHttp')->willReturn(true);

        return new WebSocketServer(
            new Configurator(),
            $authenticator,
            $router,
            new NullLogger(),
        );
    }
}
