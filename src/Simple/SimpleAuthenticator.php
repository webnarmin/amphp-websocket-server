<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Simple;

use Amp\Http\Server\Request;
use InvalidArgumentException;
use JsonException;
use webnarmin\AmphpWS\Contracts\Authenticator;
use webnarmin\AmphpWS\Contracts\WebsocketUser;

class SimpleAuthenticator implements Authenticator
{
    private string $httpAuthToken;
    private string $websocketSecret;
    private int $defaultTtlSeconds;

    public function __construct(string $httpAuthToken, string $websocketSecret, int $defaultTtlSeconds = 3600)
    {
        if ($httpAuthToken === '') {
            throw new InvalidArgumentException('Control HTTP auth token must not be empty.');
        }

        if ($websocketSecret === '') {
            throw new InvalidArgumentException('WebSocket signing secret must not be empty.');
        }

        if ($defaultTtlSeconds <= 0) {
            throw new InvalidArgumentException('Default token TTL must be a positive integer.');
        }

        $this->httpAuthToken = $httpAuthToken;
        $this->websocketSecret = $websocketSecret;
        $this->defaultTtlSeconds = $defaultTtlSeconds;
    }

    public function authenticateControlHttp(Request $request): bool
    {
        return hash_equals($this->httpAuthToken, (string)$request->getHeader('Authorization'));
    }

    public function authenticateWebSocket(Request $request): ?WebsocketUser
    {
        $token = (string)$request->getQueryParameter('token');
        if ($token === '') {
            return null;
        }

        $payload = $this->decodeToken($token);
        if ($payload === null) {
            return null;
        }

        $userId = $payload['uid'] ?? null;
        $expiresAt = $payload['exp'] ?? null;
        if (!is_int($userId) || $userId <= 0 || !is_int($expiresAt) || $expiresAt < time()) {
            return null;
        }

        return new SimpleWebsocketUser($userId);
    }

    public function issueWebSocketToken(int $userId, ?int $expiresAt = null): string
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User id must be a positive integer.');
        }

        $payload = $this->base64UrlEncode(json_encode([
            'uid' => $userId,
            'exp' => $expiresAt ?? (time() + $this->defaultTtlSeconds),
        ], JSON_THROW_ON_ERROR));

        return $payload . '.' . $this->base64UrlEncode($this->sign($payload));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        $expectedSignature = $this->base64UrlEncode($this->sign($payload));
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $json = $this->base64UrlDecode($payload);
        if ($json === null) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->websocketSecret, true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder === 1) {
            return null;
        }

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
