# AmPHP WebSocket Server

A flexible and efficient WebSocket server implementation using the Amp concurrency framework for PHP. This library enables developers to create real-time, interactive web applications with ease, providing features such as authentication, message handling, broadcasting, and more. It is designed to be scalable and efficient, making it ideal for high-performance applications.

## Features

- **Easy Setup**: Minimal configuration required to start.
- **Authentication**: Supports authentication for WebSocket and HTTP control requests.
- **Message Handling**: Customizable actions for client messages.
- **Broadcasting**: Send messages to multiple clients at once.
- **Secure Connections**: Optional SSL/TLS support.
- **Extensible**: Easily extend and customize.

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage](#usage)
  - [Server-side Setup](#server-side-setup)
  - [Client-side Usage](#client-side-usage)
  - [Broadcasting from CLI](#broadcasting-from-cli)
- [Configuration](#configuration)
- [Contributing](#contributing)
- [License](#license)

## Installation

Install via Composer:

```bash
composer require webnarmin/amphp-websocket-server
```

## Quick Start

### 1. Create a WebSocket Server Class

First, extend the `WebSocketServer` class and define your message handlers:

```php
use webnarmin\AmphpWS\WebSocketServer;
use webnarmin\AmphpWS\Contracts\WebsocketUser;

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
}
```

### 2. Set Up and Run the Server

Next, configure and run your WebSocket server:

```php
use webnarmin\AmphpWS\Configurator;
use webnarmin\AmphpWS\Simple\SimpleAuthenticator;
use webnarmin\Cryptor\Cryptor;

$config = [
    'websocket' => ['host' => '127.0.0.1', 'port' => 1337],
    'allow_origins' => ['http://127.0.0.1:8000', 'http://localhost:8000'],
];
$configurator = new Configurator($config);
$cryptor = new Cryptor('your-private-key');
$authenticator = new SimpleAuthenticator('control-http-auth-token', $cryptor);

$server = new MyWebSocketServer($configurator, $authenticator);
$server->run();
```

### Note on `SimpleAuthenticator` and `SimpleWebsocketUser`

The classes `SimpleAuthenticator` and `SimpleWebsocketUser` are provided as basic examples. They cover essential functionalities but can be extended or replaced with custom implementations to fit specific needs.

## Usage

### Server-side Setup

To create a custom WebSocket server, extend the `WebSocketServer` class and implement your desired message handlers:

```php
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
}
```

### Client-side Usage

Connect to the WebSocket server from your client-side JavaScript:

```javascript
const socket = new WebSocket('ws://127.0.0.1:1337/ws?token=WEBSOCKET_TOKEN&publicKey=WEBSOCKET_PUBLIC_KEY');

socket.onopen = () => console.log('Connected to server');
socket.onmessage = (event) => console.log('Received:', event.data);

socket.send(JSON.stringify({ action: 'echo', payload: { message: 'Hello, WebSocket!' }}));
```

#### Token and Public Key

Generate the token and public key on the server side:

```php
use webnarmin\Cryptor\Cryptor;

$cryptor = new Cryptor('websocket-private-key');
$publicKey = 'websocket-public-key';

$userId = time(); // Or any unique user identifier
$websocketToken = $cryptor->encrypt($userId, $publicKey);
```

Pass these values to your client-side code for connection.

### Broadcasting from CLI

Create a PHP script to broadcast messages from the command line:

```php
<?php
use GuzzleHttp\Client;
use webnarmin\AmphpWS\WebsocketControlHttpClient;
use Psr\Log\NullLogger;

require '../vendor/autoload.php';

$baseUri = 'http://127.0.0.1:8080';
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

// Send a broadcast message
$success = $client->broadcastText('Hello, everyone!');

if ($success) {
    echo "Message broadcasted successfully.";
} else {
    echo "Failed to broadcast message.";
}

// Send a targeted message
$success = $client->sendText(1, 'Hello, User 1!');

if ($success) {
    echo "Message sent to user 1 successfully.";
} else {
    echo "Failed to send message to user 1.";
}

// Send a binary broadcast message
$binaryData = file_get_contents('path/to/file');
$success = $client->broadcastBinary($binaryData);

if ($success) {
    echo "Binary data broadcasted successfully.";
} else {
    echo "Failed to broadcast binary data.";
}

// Multicast a text message
$success = $client->multicastText('Hello, selected users!', [1, 2, 3]);

if ($success) {
    echo "Multicast message sent successfully.";
} else {
    echo "Failed to send multicast message.";
}

// Multicast a binary message
$binaryData = file_get_contents('path/to/file');
$success = $client->multicastBinary($binaryData, [1, 2, 3]);

if ($success) {
    echo "Binary data multicast successfully.";
} else {
    echo "Failed to multicast binary data.";
}
```

Run this script from the command line to broadcast a message to all connected clients.

## Configuration

Configuration options can be set when creating the `Configurator` instance:

```php
$config = [
    'websocket' => [
        'host' => '127.0.0.1',
        'port' => 1337,
        'use_ssl' => false,
        'ssl_cert' => null,
        'ssl_key' => null,
    ],
    'allow_origins' => ['*'],
    'max_connections' => 1000,
    'max_connections_per_ip' => 10,
    'timeout' => 60,
];

$configurator = new Configurator($config);
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License.