<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Control;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use webnarmin\AmphpWS\WebsocketControlHttpClient;

final class WebsocketControlHttpClientTest extends TestCase
{
    public function test_client_serializes_send_text_and_returns_success_result(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('POST', '/send-text', [
                'json' => [
                    'userId' => 123,
                    'payload' => 'hello',
                ],
            ])
            ->willReturn(new Response(200, [], '{"status":"success"}'));

        $result = (new WebsocketControlHttpClient($client, new NullLogger()))->sendText(123, 'hello');

        self::assertTrue($result->isSuccess());
        self::assertSame(200, $result->getStatusCode());
    }

    public function test_client_parses_error_response(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willReturn(new Response(400, [], json_encode([
            'status' => 'error',
            'error' => [
                'code' => 'invalid_control_request',
                'message' => 'Bad request.',
            ],
        ], JSON_THROW_ON_ERROR)));

        $result = (new WebsocketControlHttpClient($client, new NullLogger()))->broadcastText('hello');

        self::assertFalse($result->isSuccess());
        self::assertSame('invalid_control_request', $result->getErrorCode());
        self::assertSame('Bad request.', $result->getMessage());
    }

    public function test_client_returns_failure_result_on_transport_exception(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willThrowException(new class ('down') extends \Exception implements GuzzleException {
        });

        $result = (new WebsocketControlHttpClient($client, new NullLogger()))->broadcastText('hello');

        self::assertFalse($result->isSuccess());
        self::assertSame('request_failed', $result->getErrorCode());
    }
}
