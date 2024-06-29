<?php declare(strict_types=1);

use GuzzleHttp\Client;
use webnarmin\AmphpWS\WebsocketControlHttpClient;
use Psr\Log\NullLogger;

require '../vendor/autoload.php';

$baseUri = 'http://127.0.0.1:1337';
$authToken = 'control-http-auth-token';

// Create a Guzzle HTTP client instance
$httpClient = new Client([
    'base_uri' => $baseUri,
    'headers' => [
        'Authorization' => $authToken,
        'Content-Type' => 'application/json',
    ],
]);

$client = new WebsocketControlHttpClient($httpClient, new NullLogger());

$success = $client->broadcastText('Hello, everyone!');

if ($success) {
    echo "Message broadcasted successfully.";
} else {
    echo "Failed to broadcast message.";
}
