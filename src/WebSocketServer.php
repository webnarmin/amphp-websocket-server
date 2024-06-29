<?php declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware\AllowedMethodsMiddleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\InternetAddress;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Amp\Http\Server\Router;
use Amp\Websocket\Server\AllowOriginAcceptor;
use Amp\Websocket\Server\Websocket;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\WebsocketClient;
use webnarmin\AmphpWS\Contracts\Authenticator;
use webnarmin\AmphpWS\Contracts\WebsocketUser;

abstract class WebSocketServer implements WebsocketClientHandler
{
    private Configurator $configurator;
    private Authenticator $authenticator;
    private LoggerInterface $logger;
    protected UserAwareWebsocketClientGateway $gateway;

    public function __construct(
        Configurator $configurator,
        Authenticator $authenticator,
        ?LoggerInterface $logger = null
    ) {
        $this->configurator = $configurator;
        $this->authenticator = $authenticator;
        $this->logger = $logger ?? new NullLogger();
        $this->gateway = new UserAwareWebsocketClientGateway(new WebsocketClientGateway());
    }

    public function run(): void
    {
        $this->logger->info("Starting WebSocket server.");
        $server = $this->createHttpServer();
        $websocket = $this->createWebsocket($server);

        $router = $this->createRouter($server, $websocket);

        $server->start($router, new DefaultErrorHandler());
        $this->logger->info("WebSocket server started.");

        $this->awaitSignalAndStopServer($server);
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
                (new \Amp\Socket\ServerTlsContext)->withDefaultCertificate($cert)
            );
        }

        $server = SocketHttpServer::createForDirectAccess(
            $this->logger,
            true, // enableCompression
            $this->configurator->getMaxConnections(),
            $this->configurator->getMaxConnectionsPerIp(),
            $this->configurator->getMaxConnections(), // concurrencyLimit
            AllowedMethodsMiddleware::DEFAULT_ALLOWED_METHODS,
            new DefaultHttpDriverFactory(
                $this->logger,
                HttpDriver::DEFAULT_STREAM_TIMEOUT,
                $this->configurator->getTimeout(),
            )
        );

        $server->expose(new InternetAddress($wsAddress['host'], $wsAddress['port']), $context);

        $this->logger->info(
            ($useSSL ? "Secure" : "Insecure") . 
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
    
        $middleware = new ControlHttpRequestAuthMiddleware($this->authenticator, $this->logger);
        $router->addMiddleware($middleware);

        $this->addControlHttpRoutes($router);
    
        return $router;
    }

    private function addControlHttpRoutes(Router $router): void
    {
        $routes = [
            'POST /send-text' => function ($data) {
                return $this->gateway->sendText($data['payload'], $data['userId']);
            },
            'POST /broadcast-text' => function ($data) {
                return $this->gateway->broadcastText($data['payload'], $data['excludedUserIds'] ?? []);
            },
            'POST /broadcast-binary' => function ($data) {
                return $this->gateway->broadcastBinary(base64_decode($data['payload']), $data['excludedUserIds'] ?? []);
            },
            'POST /multicast-text' => function ($data) {
                return $this->gateway->multicastText($data['payload'], $data['userIds']);
            },
            'POST /multicast-binary' => function ($data) {
                return $this->gateway->multicastBinary(base64_decode($data['payload']), $data['userIds']);
            },
        ];
    
        foreach ($routes as $route => $handler) {
            [$method, $path] = explode(' ', $route);
            $router->addRoute($method, $path, new ClosureRequestHandler(
                function (Request $request) use ($handler) {
                    try {
                        $data = json_decode($request->getBody()->buffer(), true);
                        if (!$data) {
                            throw new \InvalidArgumentException("Invalid JSON data");
                        }
                        $future = $handler($data);
                        $future->await();
                        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'success']));
                    } catch (\Throwable $e) {
                        $this->logger->error("Error processing request: " . $e->getMessage());
                        return new Response(400, ['Content-Type' => 'application/json'], json_encode(['status' => 'error', 'message' => $e->getMessage()]));
                    }
                }
            ));
        }
    }

    private function awaitSignalAndStopServer(SocketHttpServer $server): void
    {
        $signal = \Amp\trapSignal([SIGINT, SIGTERM]);
        $this->logger->info("Signal received ({$signal}), stopping server.");
        $server->stop();
        $this->logger->info("Server stopped.");
    }

    public function handleClient(WebsocketClient $client, Request $request, Response $response): void
    {
        $clientId = $client->getId();
        $this->logger->info("New client connected. ID: {$clientId}");

        if ($user = $this->authenticateClient($client, $request)) {
            $this->logger->info("Client authenticated. ID: {$clientId}, User ID: {$user->getId()}");
            $this->processClientMessages($client);
        } else {
            $this->logger->warning("Client authentication failed. ID: {$clientId}");
        }
    }

    private function authenticateClient(WebsocketClient $client, Request $request): ?WebsocketUser
    {
        $user = $this->authenticator->authenticateWebSocket($request);

        if (!$user || $user->getId() === null) {
            $client->close(1008, 'Authentication failed');
            return null;
        }

        $this->gateway->addClient($client, $user);
        return $user;
    }

    private function processClientMessages(WebsocketClient $client): void
    {
        try {
            while ($message = $client->receive()) {
                $this->handleClientMessage($client, $message->buffer());
            }
        } catch (\Exception $e) {
            $this->logger->error("Error processing messages for client. ID: " . $client->getId() . ". Error: " . $e->getMessage());
        }
    }

    private function handleClientMessage(WebsocketClient $client, string $messageBuffer): void
    {
        $data = json_decode($messageBuffer, true);
        if ($this->isValidMessage($data)) {
            $this->processMessageAction($client, $data);
        } else {
            $this->logger->warning("Invalid message from client. ID: " . $client->getId());
            $userId = $this->gateway->getUserIdByClientId($client->getId());
            $this->gateway->sendText(json_encode(['status' => 'error', 'payload' => "Invalid request"]), $userId);
        }
    }

    private function isValidMessage(?array $data): bool
    {
        return $data && isset($data['action'], $data['payload']);
    }

    private function processMessageAction(WebsocketClient $client, array $data): void
    {
        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $data['action'])));

        if (method_exists($this, $methodName)) {
            $this->executeMessageAction($client, $methodName, $data['payload']);
        } else {
            $userId = $this->gateway->getUserIdByClientId($client->getId());
            $this->logger->warning("Unsupported action: " . $data['action'] . ". Client ID: " . $client->getId() . ", User ID: " . $userId);
            $this->gateway->sendText(json_encode(['status' => 'error', 'payload' => "Action not supported"]), $userId);
        }
    }

    private function executeMessageAction(WebsocketClient $client, string $methodName, array $payload): void
    {
        $clientId = $client->getId();
        $userId = $this->gateway->getUserIdByClientId($clientId);
        $user = $this->gateway->getUserByClientId($clientId);

        try {
            $result = $this->$methodName($user, $payload);
            $this->gateway->sendText(json_encode(['status' => 'success', 'payload' => $result]), $userId);
        } catch (\Exception $e) {
            $this->logger->error("Error executing action. Client ID: {$clientId}, User ID: {$userId}, Action: {$methodName}, Error: " . $e->getMessage());
            $this->gateway->sendText(json_encode(['status' => 'error', 'payload' => "Error processing request: " . $e->getMessage()]), $userId);
        }
    }
}