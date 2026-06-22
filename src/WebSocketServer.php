<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware\AllowedMethodsMiddleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\InternetAddress;
use Amp\Websocket\Server\AllowOriginAcceptor;
use Amp\Websocket\Server\Websocket;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\WebsocketClient;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use webnarmin\AmphpWS\Contracts\Authenticator;
use webnarmin\AmphpWS\Contracts\WebsocketUser;
use webnarmin\AmphpWS\Control\ControlHttpRequestValidator;
use webnarmin\AmphpWS\Exception\AuthenticationException;
use webnarmin\AmphpWS\Exception\ControlHttpException;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;

class WebSocketServer implements WebsocketClientHandler
{
    private LoggerInterface $logger;
    private UserAwareWebsocketClientGateway $gateway;
    private ServerHooks $hooks;
    private MessageProcessor $messageProcessor;
    private ControlHttpRequestValidator $controlValidator;

    public function __construct(
        private Configurator $configurator,
        private Authenticator $authenticator,
        private ActionRouter $router,
        ?LoggerInterface $logger = null,
        ?ServerHooks $hooks = null,
        ?UserAwareWebsocketClientGateway $gateway = null,
        ?MessageProcessor $messageProcessor = null,
        ?ControlHttpRequestValidator $controlValidator = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->hooks = $hooks ?? ServerHooks::none();
        $this->gateway = $gateway ?? new UserAwareWebsocketClientGateway();
        $this->messageProcessor = $messageProcessor ?? new MessageProcessor($this->router, $this->logger, $this->hooks);
        $this->controlValidator = $controlValidator ?? new ControlHttpRequestValidator();
    }

    public function run(): void
    {
        $this->logger->info('Starting WebSocket server.');
        $server = $this->createHttpServer();
        $websocket = $this->createWebsocket($server);
        $router = $this->createRouter($server, $websocket);

        $server->start($router, new DefaultErrorHandler());
        $this->logger->info('WebSocket server started.');

        $this->awaitSignalAndStopServer($server);
    }

    public function getGateway(): UserAwareWebsocketClientGateway
    {
        return $this->gateway;
    }

    public function processRawMessage(WebsocketUser $user, string $messageBuffer, int $clientId): ?ResponseEnvelope
    {
        return $this->messageProcessor->process($user, $messageBuffer, $clientId);
    }

    public function handleClient(WebsocketClient $client, Request $request, Response $response): void
    {
        $clientId = $client->getId();
        $this->logger->info('New client connected.', ['client_id' => $clientId]);

        $user = $this->authenticateClient($client, $request);
        if ($user === null) {
            return;
        }

        $this->logger->info('Client authenticated.', [
            'client_id' => $clientId,
            'user_id' => $user->getId(),
        ]);

        $this->gateway->addClient($client, $user);
        $this->hooks->onAuthenticated($user, $client);
        $client->onClose(function () use ($user, $clientId): void {
            $this->hooks->onDisconnected($user, $clientId);
        });

        $this->processClientMessages($client, $user);
    }

    private function createHttpServer(): SocketHttpServer
    {
        $wsAddress = $this->configurator->getWebSocketAddress();
        $useSSL = $this->configurator->isUseSSL();
        $sslCert = $this->configurator->getSSLCertFile();
        $sslKey = $this->configurator->getSSLKeyFile();

        $context = new \Amp\Socket\BindContext();

        if ($useSSL && $sslCert && $sslKey) {
            $cert = new \Amp\Socket\Certificate($sslCert, $sslKey);
            $context = $context->withTlsContext(
                (new \Amp\Socket\ServerTlsContext())->withDefaultCertificate($cert)
            );
        }

        $server = SocketHttpServer::createForDirectAccess(
            $this->logger,
            true,
            $this->configurator->getMaxConnections(),
            $this->configurator->getMaxConnectionsPerIp(),
            $this->configurator->getMaxConnections(),
            AllowedMethodsMiddleware::DEFAULT_ALLOWED_METHODS,
            new DefaultHttpDriverFactory(
                $this->logger,
                HttpDriver::DEFAULT_STREAM_TIMEOUT,
                $this->configurator->getTimeout(),
            )
        );

        $server->expose(new InternetAddress($wsAddress['host'], $wsAddress['port']), $context);

        $this->logger->info(
            ($useSSL ? 'Secure' : 'Insecure') .
            " HTTP server exposed on: {$wsAddress['host']}:{$wsAddress['port']}"
        );

        return $server;
    }

    private function createWebsocket(SocketHttpServer $server): Websocket
    {
        $acceptor = new AllowOriginAcceptor($this->configurator->getAllowOrigins());

        return new Websocket($server, $this->logger, $acceptor, $this);
    }

    private function createRouter(SocketHttpServer $server, Websocket $websocket): Router
    {
        $router = new Router($server, $this->logger, new DefaultErrorHandler());
        $router->addRoute('GET', '/ws', $websocket);
        $router->addMiddleware(new ControlHttpRequestAuthMiddleware($this->authenticator, $this->logger));
        $this->addControlHttpRoutes($router);

        return $router;
    }

    private function addControlHttpRoutes(Router $router): void
    {
        foreach ([
            'send-text',
            'broadcast-text',
            'broadcast-binary',
            'multicast-text',
            'multicast-binary',
        ] as $operation) {
            $router->addRoute('POST', '/' . $operation, new ClosureRequestHandler(
                fn (Request $request): Response => $this->handleControlHttpRequest($operation, $request)
            ));
        }
    }

    private function handleControlHttpRequest(string $operation, Request $request): Response
    {
        try {
            $data = json_decode($request->getBody()->buffer(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_is_list($data)) {
                throw new ControlHttpException('invalid_control_request', 'Control request body must be a JSON object.');
            }

            /** @var array<string, mixed> $data */
            $validated = $this->controlValidator->validate($operation, $data);
            $this->dispatchControlOperation($operation, $validated)->await();

            return $this->jsonResponse(200, ['status' => 'success']);
        } catch (JsonException) {
            return $this->jsonError(400, 'invalid_json', 'Invalid JSON request body.');
        } catch (ControlHttpException $exception) {
            return $this->jsonError($exception->getStatusCode(), $exception->getErrorCode(), $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->error('Error processing control HTTP request.', [
                'operation' => $operation,
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonError(400, 'invalid_control_request', $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function dispatchControlOperation(string $operation, array $data): \Amp\Future
    {
        return match ($operation) {
            'send-text' => $this->gateway->sendText($data['userId'], $data['payload']),
            'broadcast-text' => $this->gateway->broadcastText($data['payload'], $data['excludedUserIds']),
            'broadcast-binary' => $this->gateway->broadcastBinary($data['payload'], $data['excludedUserIds']),
            'multicast-text' => $this->gateway->multicastText($data['payload'], $data['userIds']),
            'multicast-binary' => $this->gateway->multicastBinary($data['payload'], $data['userIds']),
            default => throw new ControlHttpException(
                'invalid_control_request',
                "Unsupported control operation '{$operation}'.",
                404,
            ),
        };
    }

    private function authenticateClient(WebsocketClient $client, Request $request): ?WebsocketUser
    {
        $user = $this->authenticator->authenticateWebSocket($request);
        if ($user !== null) {
            return $user;
        }

        $exception = new AuthenticationException($client->getId());
        $this->logger->warning($exception->getMessage(), ['client_id' => $client->getId()]);
        $this->hooks->onUnhandledException(null, $client->getId(), $exception);
        $client->close(1008, 'Authentication failed');

        return null;
    }

    private function processClientMessages(WebsocketClient $client, WebsocketUser $user): void
    {
        try {
            while ($message = $client->receive()) {
                $response = $this->processRawMessage($user, $message->buffer(), $client->getId());
                if ($response !== null) {
                    $this->gateway->sendText($user->getId(), $response->toJson())->await();
                }
            }
        } catch (Throwable $exception) {
            $this->logger->error('Error processing client messages.', [
                'client_id' => $client->getId(),
                'user_id' => $user->getId(),
                'message' => $exception->getMessage(),
            ]);
            $this->hooks->onUnhandledException($user, $client->getId(), $exception);
        }
    }

    private function awaitSignalAndStopServer(SocketHttpServer $server): void
    {
        $signal = \Amp\trapSignal([SIGINT, SIGTERM]);
        $this->logger->info("Signal received ({$signal}), stopping server.");
        $server->stop();
        $this->logger->info('Server stopped.');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonResponse(int $status, array $body): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR)
        );
    }

    private function jsonError(int $status, string $code, string $message): Response
    {
        return $this->jsonResponse($status, [
            'status' => 'error',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }
}
