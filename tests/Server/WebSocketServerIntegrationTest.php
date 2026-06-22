<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Server;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Router;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\InternetAddress;

use function Amp\Websocket\Client\connect;

use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\Server\Rfc6455Acceptor;
use Amp\Websocket\Server\Websocket;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use webnarmin\AmphpWS\ActionRouter;
use webnarmin\AmphpWS\Configurator;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;
use webnarmin\AmphpWS\Simple\SimpleAuthenticator;
use webnarmin\AmphpWS\WebSocketServer;

final class WebSocketServerIntegrationTest extends TestCase
{
    public function test_real_websocket_client_can_send_envelope_and_receive_response(): void
    {
        $logger = new NullLogger();
        $httpServer = SocketHttpServer::createForDirectAccess($logger);
        $httpServer->expose(new InternetAddress('127.0.0.1', 0));

        $authenticator = new SimpleAuthenticator('control-token', 'websocket-secret');
        $router = new ActionRouter();
        $router->on('echo', static fn ($user, $message): ResponseEnvelope => ResponseEnvelope::success([
            'userId' => $user->getId(),
            'message' => $message->getPayload()['message'] ?? '',
        ]));

        $webSocketServer = new WebSocketServer(
            new Configurator(),
            $authenticator,
            $router,
            $logger,
        );

        $errorHandler = new DefaultErrorHandler();
        $websocket = new Websocket($httpServer, $logger, new Rfc6455Acceptor(), $webSocketServer);
        $httpRouter = new Router($httpServer, $logger, $errorHandler);
        $httpRouter->addRoute('GET', '/ws', $websocket);

        $httpServer->start($httpRouter, $errorHandler);

        try {
            $address = $httpServer->getServers()[0]->getAddress();
            self::assertInstanceOf(InternetAddress::class, $address);

            $token = $authenticator->issueWebSocketToken(321, time() + 60);
            $connection = connect(new WebsocketHandshake(sprintf(
                'ws://127.0.0.1:%d/ws?token=%s',
                $address->getPort(),
                urlencode($token),
            )));

            $connection->sendText(json_encode([
                'action' => 'echo',
                'payload' => ['message' => 'hello'],
                'requestId' => 'req-e2e',
            ], JSON_THROW_ON_ERROR));

            $message = $connection->receive();
            self::assertNotNull($message);

            $response = json_decode($message->buffer(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('success', $response['status'] ?? null);
            self::assertSame('req-e2e', $response['requestId'] ?? null);
            self::assertSame(321, $response['payload']['userId'] ?? null);
            self::assertSame('hello', $response['payload']['message'] ?? null);

            $connection->close();
        } finally {
            $httpServer->stop();
        }
    }
}
