<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Protocol;

use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\Exception\ProtocolException;
use webnarmin\AmphpWS\Protocol\MessageEnvelope;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;

final class MessageEnvelopeTest extends TestCase
{
    public function test_valid_message_json_roundtrip(): void
    {
        $message = MessageEnvelope::fromJson(json_encode([
            'action' => 'echo',
            'payload' => ['message' => 'hello'],
            'requestId' => 'req-1',
            'metadata' => ['source' => 'test'],
        ], JSON_THROW_ON_ERROR));

        self::assertSame('echo', $message->getAction());
        self::assertSame(['message' => 'hello'], $message->getPayload());
        self::assertSame('req-1', $message->getRequestId());
        self::assertSame(['source' => 'test'], $message->getMetadata());
        self::assertSame($message->toArray(), MessageEnvelope::fromJson($message->toJson())->toArray());
    }

    public function test_invalid_json_is_rejected(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid JSON message.');

        MessageEnvelope::fromJson('{bad');
    }

    public function test_missing_action_is_rejected(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Message action must be a non-empty string.');

        MessageEnvelope::fromArray(['payload' => []]);
    }

    public function test_non_object_payload_is_rejected(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Message payload must be an object.');

        MessageEnvelope::fromArray(['action' => 'echo', 'payload' => ['list-item']]);
    }

    public function test_response_json_roundtrip(): void
    {
        $response = ResponseEnvelope::success(['ok' => true], 'req-1');
        $decoded = ResponseEnvelope::fromJson($response->toJson());

        self::assertTrue($decoded->isSuccess());
        self::assertSame(['ok' => true], $decoded->getPayload());
        self::assertSame('req-1', $decoded->getRequestId());

        $error = ResponseEnvelope::error('unsupported_action', 'No handler.', 'req-2');
        self::assertSame($error->toArray(), ResponseEnvelope::fromJson($error->toJson())->toArray());
    }
}
