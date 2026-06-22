# AmPHP WebSocket Server

Authenticated WebSocket transport runtime on top of Amp.

This package provides:

- WebSocket server bootstrap using `amphp/websocket-server`.
- Authentication for WebSocket clients and HTTP control requests.
- Strict JSON message envelopes.
- Explicit action routing.
- User-aware gateway for direct, multicast and broadcast delivery.
- HTTP control endpoints for pushing messages from another process.

It does not provide application-level chat flows, UI rendering, persistence, rooms, bot logic or framework-specific abstractions. Libraries such as ChatFlow can use this package as a transport layer.

## Installation

```bash
composer require webnarmin/amphp-websocket-server
```

## Quick Start

```php
use webnarmin\AmphpWS\ActionRouter;
use webnarmin\AmphpWS\Configurator;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;
use webnarmin\AmphpWS\Simple\SimpleAuthenticator;
use webnarmin\AmphpWS\WebSocketServer;

$router = new ActionRouter();

$router->on('echo', static function ($user, $message): ResponseEnvelope {
    return ResponseEnvelope::success([
        'message' => 'Echo: ' . ($message->getPayload()['message'] ?? ''),
    ]);
});

$server = new WebSocketServer(
    new Configurator([
        'websocket' => ['host' => '127.0.0.1', 'port' => 1337],
        'allow_origins' => ['http://127.0.0.1:8000'],
    ]),
    new SimpleAuthenticator('control-http-auth-token', 'websocket-signing-secret'),
    $router,
);

$server->run();
```

## Message Protocol

Clients send JSON objects:

```json
{
  "action": "echo",
  "payload": {"message": "Hello"},
  "requestId": "req-1",
  "metadata": {"source": "browser"}
}
```

Rules:

- `action` is required and must be a non-empty string.
- `payload` is optional and must be a JSON object when present.
- `requestId` is optional and must be a non-empty string when present.
- `metadata` is optional and must be a JSON object when present.

Server responses use:

```json
{
  "status": "success",
  "payload": {"message": "Echo: Hello"},
  "requestId": "req-1"
}
```

Errors use:

```json
{
  "status": "error",
  "payload": {},
  "requestId": "req-1",
  "error": {"code": "unsupported_action", "message": "Action 'x' is not supported."}
}
```

Standard error codes:

- `invalid_json`
- `invalid_message`
- `unsupported_action`
- `handler_failed`
- `authentication_failed`
- `invalid_control_request`

## Action Handlers

Handlers may be callables or implement `MessageHandlerInterface`.

```php
$router->on('sum', static function ($user, $message): ResponseEnvelope {
    $numbers = $message->getPayload()['numbers'] ?? [];

    return ResponseEnvelope::success([
        'result' => is_array($numbers) ? array_sum($numbers) : 0,
    ]);
});
```

Return `null` when no response should be sent. Return `ResponseEnvelope::success([])` when an explicit empty success response should be sent.

## Authentication

Implement `Authenticator`:

```php
interface Authenticator
{
    public function authenticateControlHttp(Request $request): bool;
    public function authenticateWebSocket(Request $request): ?WebsocketUser;
}
```

`WebsocketUser::getId()` returns a non-null positive integer. If WebSocket authentication fails, the client is closed with policy violation code `1008`.

`SimpleAuthenticator` is a minimal HMAC-signed token example. For production systems, implement `Authenticator` against your own session, API token or identity provider.

## Gateway

Use `getGateway()` to send messages to authenticated users:

```php
$server->getGateway()->sendText(123, 'Hello user');
$server->getGateway()->multicastText('Hello team', [123, 456]);
$server->getGateway()->broadcastText('Hello everyone', excludedUserIds: [123]);
```

If a user has no active client, sending is a no-op handled by Amp's gateway. Invalid user ids are rejected with `GatewayException`.

## HTTP Control

Authenticated POST endpoints:

- `/send-text`
- `/broadcast-text`
- `/broadcast-binary`
- `/multicast-text`
- `/multicast-binary`

Successful response:

```json
{"status":"success"}
```

Error response:

```json
{"status":"error","error":{"code":"invalid_control_request","message":"payload must be a string."}}
```

Use `WebsocketControlHttpClient` from CLI or workers:

```php
$result = $client->sendText(123, 'Hello');

if (!$result->isSuccess()) {
    echo $result->getMessage();
}
```

## Lifecycle Hooks

`ServerHooks` supports optional callbacks:

- authenticated: called after a client is authenticated and registered.
- disconnected: called when a registered client disconnects.
- invalidMessage: called when inbound JSON/protocol validation fails.
- unhandledException: called for authentication and handler/runtime failures.

## Examples

- `example/server.php` starts a router-based server.
- `example/public/index.php` is a browser test client.
- `example/broadcast_from_cli.php` sends a control HTTP broadcast.

## Quality

```bash
composer check
composer cs-check
```
