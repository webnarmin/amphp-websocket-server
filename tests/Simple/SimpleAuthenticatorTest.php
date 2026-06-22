<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Simple;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\Simple\SimpleAuthenticator;

final class SimpleAuthenticatorTest extends TestCase
{
    public function test_control_http_token_is_validated(): void
    {
        $authenticator = new SimpleAuthenticator('control-token', 'websocket-secret');

        self::assertTrue($authenticator->authenticateControlHttp($this->request('/ws', [
            'authorization' => ['control-token'],
        ])));
        self::assertFalse($authenticator->authenticateControlHttp($this->request('/ws', [
            'authorization' => ['wrong'],
        ])));
    }

    public function test_websocket_token_is_issued_and_validated(): void
    {
        $authenticator = new SimpleAuthenticator('control-token', 'websocket-secret');
        $token = $authenticator->issueWebSocketToken(42, time() + 60);

        $user = $authenticator->authenticateWebSocket($this->request('/ws?token=' . urlencode($token)));

        self::assertNotNull($user);
        self::assertSame(42, $user->getId());
    }

    public function test_websocket_token_rejects_wrong_secret_and_expiration(): void
    {
        $authenticator = new SimpleAuthenticator('control-token', 'websocket-secret');
        $token = $authenticator->issueWebSocketToken(42, time() + 60);

        self::assertNull((new SimpleAuthenticator('control-token', 'wrong-secret'))
            ->authenticateWebSocket($this->request('/ws?token=' . urlencode($token))));

        $expiredToken = $authenticator->issueWebSocketToken(42, time() - 1);
        self::assertNull($authenticator->authenticateWebSocket($this->request('/ws?token=' . urlencode($expiredToken))));
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function request(string $target, array $headers = []): Request
    {
        return new Request(
            $this->createMock(Client::class),
            'GET',
            \League\Uri\Http::new($target),
            $headers,
        );
    }
}
