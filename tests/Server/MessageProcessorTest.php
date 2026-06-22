<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Server;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use webnarmin\AmphpWS\ActionRouter;
use webnarmin\AmphpWS\MessageProcessor;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;
use webnarmin\AmphpWS\ServerHooks;
use webnarmin\AmphpWSTest\Fakes\FakeWebsocketUser;

final class MessageProcessorTest extends TestCase
{
    public function test_success_response_keeps_empty_payload_and_request_id(): void
    {
        $router = new ActionRouter();
        $router->on('empty', static fn (): ResponseEnvelope => ResponseEnvelope::success([]));

        $response = (new MessageProcessor($router))->process(
            new FakeWebsocketUser(),
            '{"action":"empty","payload":{},"requestId":"req-1"}',
            77
        );

        self::assertNotNull($response);
        self::assertTrue($response->isSuccess());
        self::assertSame([], $response->getPayload());
        self::assertSame('req-1', $response->getRequestId());
    }

    public function test_invalid_message_calls_hook_and_returns_error(): void
    {
        $trace = new ArrayObject();
        $hooks = new ServerHooks(
            invalidMessage: static function ($user, $clientId, $exception) use ($trace): void {
                $trace->append([$clientId, $exception->getErrorCode()]);
            }
        );

        $response = (new MessageProcessor(new ActionRouter(), new NullLogger(), $hooks))
            ->process(new FakeWebsocketUser(), '{bad', 88);

        self::assertNotNull($response);
        self::assertSame('invalid_json', $response->getError()['code'] ?? null);
        self::assertSame([[88, 'invalid_json']], $trace->getArrayCopy());
    }

    public function test_handler_failure_returns_handler_failed(): void
    {
        $router = new ActionRouter();
        $router->on('fail', static fn (): ResponseEnvelope => throw new RuntimeException('boom'));

        $response = (new MessageProcessor($router))->process(
            new FakeWebsocketUser(),
            '{"action":"fail","payload":{},"requestId":"req-2"}',
            99
        );

        self::assertNotNull($response);
        self::assertSame('handler_failed', $response->getError()['code'] ?? null);
        self::assertSame('req-2', $response->getRequestId());
    }
}
