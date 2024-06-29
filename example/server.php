<?php

require '../vendor/autoload.php';

use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Logger;
use Amp\ByteStream\WritableResourceStream;
use webnarmin\AmphpWS\Configurator;
use webnarmin\AmphpWS\Contracts\WebsocketUser;
use webnarmin\AmphpWS\Simple\SimpleAuthenticator;
use webnarmin\AmphpWS\WebSocketServer;
use webnarmin\Cryptor\Cryptor;

class MyWebSocketServer extends WebSocketServer
{
    protected function handleEcho(WebsocketUser $user, array $payload): array
    {
        return ['message' => 'Echo: ' . $payload['message']];
    }

    protected function handleSum(WebsocketUser $user, array $payload): array
    {
        $numbers = $payload['numbers'] ?? [];
        $sum = array_sum($numbers);
        return ['result' => $sum];
    }

    protected function handleBroadcast(WebsocketUser $user, array $payload) {
        $this->gateway->broadcastText($payload['message'], [$user->getId()]);
    }
}

// Create a logger
$logStream = new WritableResourceStream(STDOUT);
$logHandler = new StreamHandler($logStream);
$logHandler->setFormatter(new ConsoleFormatter);
$logger = new Logger('websocket-server');
$logger->pushHandler($logHandler);

// Create a configurator
$config = [
    'websocket' => [
        'host' => '127.0.0.1',
        'port' => 1337
    ],
    'allow_origins' => ['http://127.0.0.1:8000', "http://localhost:8000"],
    'max_connections' => 1000,
    'timeout' => 60,
];
$configurator = new Configurator($config);

// Create an authenticator
$cryptor = new Cryptor('websocket-private-key');
$authenticator = new SimpleAuthenticator('control-http-auth-token', $cryptor);

// Create and run the WebSocket server
$server = new MyWebSocketServer($configurator, $authenticator, $logger);
$server->run();