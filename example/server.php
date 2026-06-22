<?php

declare(strict_types=1);

require '../vendor/autoload.php';

use Amp\ByteStream\WritableResourceStream;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Logger;
use webnarmin\AmphpWS\ActionRouter;
use webnarmin\AmphpWS\Configurator;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;
use webnarmin\AmphpWS\Simple\SimpleAuthenticator;
use webnarmin\AmphpWS\WebSocketServer;

$logStream = new WritableResourceStream(STDOUT);
$logHandler = new StreamHandler($logStream);
$logHandler->setFormatter(new ConsoleFormatter());
$logger = new Logger('websocket-server');
$logger->pushHandler($logHandler);

$router = new ActionRouter();
$router->on('echo', static function ($user, $message): ResponseEnvelope {
    return ResponseEnvelope::success([
        'message' => 'Echo: ' . ($message->getPayload()['message'] ?? ''),
    ]);
});
$router->on('sum', static function ($user, $message): ResponseEnvelope {
    $numbers = $message->getPayload()['numbers'] ?? [];

    return ResponseEnvelope::success([
        'result' => is_array($numbers) ? array_sum($numbers) : 0,
    ]);
});

$server = new WebSocketServer(
    new Configurator([
        'websocket' => [
            'host' => '127.0.0.1',
            'port' => 1337,
        ],
        'allow_origins' => ['http://127.0.0.1:8000', 'http://localhost:8000'],
        'max_connections' => 1000,
        'timeout' => 60,
    ]),
    new SimpleAuthenticator('control-http-auth-token', 'websocket-signing-secret'),
    $router,
    $logger,
);

$router->on('broadcast', static function ($user, $message) use ($server): ResponseEnvelope {
    $server->getGateway()->broadcastText(
        (string) ($message->getPayload()['message'] ?? ''),
        [$user->getId()]
    );

    return ResponseEnvelope::success(['broadcasted' => true]);
});

$server->run();
