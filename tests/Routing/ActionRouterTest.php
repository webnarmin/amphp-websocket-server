<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Routing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use webnarmin\AmphpWS\ActionRouter;
use webnarmin\AmphpWS\Exception\HandlerException;
use webnarmin\AmphpWS\Protocol\MessageEnvelope;
use webnarmin\AmphpWS\Protocol\ResponseEnvelope;
use webnarmin\AmphpWSTest\Fakes\FakeWebsocketUser;

final class ActionRouterTest extends TestCase
{
    public function test_known_action_calls_handler(): void
    {
        $router = new ActionRouter();
        $router->on('echo', static fn (): ResponseEnvelope => ResponseEnvelope::success(['message' => 'ok']));

        $response = $router->dispatch(
            new FakeWebsocketUser(),
            new MessageEnvelope('echo', ['message' => 'hello'])
        );

        self::assertInstanceOf(ResponseEnvelope::class, $response);
        self::assertSame(['message' => 'ok'], $response->getPayload());
    }

    public function test_unknown_action_returns_unsupported_action(): void
    {
        $response = (new ActionRouter())->dispatch(
            new FakeWebsocketUser(),
            new MessageEnvelope('missing')
        );

        self::assertNotNull($response);
        self::assertFalse($response->isSuccess());
        self::assertSame('unsupported_action', $response->getError()['code'] ?? null);
    }

    public function test_handler_returning_null_sends_no_response(): void
    {
        $router = new ActionRouter();
        $router->on('silent', static fn (): null => null);

        self::assertNull($router->dispatch(new FakeWebsocketUser(), new MessageEnvelope('silent')));
    }

    public function test_handler_exception_is_wrapped(): void
    {
        $router = new ActionRouter();
        $router->on('fail', static fn (): ResponseEnvelope => throw new RuntimeException('boom'));

        $this->expectException(HandlerException::class);
        $this->expectExceptionMessage('boom');

        $router->dispatch(new FakeWebsocketUser(), new MessageEnvelope('fail'));
    }
}
