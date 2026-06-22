<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Psr\Log\NullLogger;
use webnarmin\AmphpWS\WebsocketControlHttpClient;

require '../vendor/autoload.php';

$httpClient = new Client([
    'base_uri' => 'http://127.0.0.1:1337',
    'headers' => [
        'Authorization' => 'control-http-auth-token',
        'Content-Type' => 'application/json',
    ],
]);

$client = new WebsocketControlHttpClient($httpClient, new NullLogger());
$result = $client->broadcastText('Hello, everyone!');

echo $result->isSuccess()
    ? "Message broadcasted successfully.\n"
    : "Failed to broadcast message: {$result->getMessage()}\n";
