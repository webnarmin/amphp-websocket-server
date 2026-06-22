<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Gateway;

use Amp\Future;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\WebsocketClient;
use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\Exception\GatewayException;
use webnarmin\AmphpWS\UserAwareWebsocketClientGateway;
use webnarmin\AmphpWSTest\Fakes\FakeWebsocketUser;

final class UserAwareWebsocketClientGatewayTest extends TestCase
{
    public function test_send_multicast_broadcast_and_disconnect_mapping(): void
    {
        $inner = $this->createMock(WebsocketGateway::class);
        $client = $this->createMock(WebsocketClient::class);
        $closeCallback = null;

        $client->method('getId')->willReturn(10);
        $client->method('onClose')->willReturnCallback(static function ($callback) use (&$closeCallback): void {
            $closeCallback = $callback;
        });

        $inner->expects(self::once())->method('addClient')->with($client);
        $inner->expects(self::exactly(2))->method('multicastText')->willReturn(Future::complete());
        $inner->expects(self::once())->method('broadcastText')->with('all', [10])->willReturn(Future::complete());

        $gateway = new UserAwareWebsocketClientGateway($inner);
        $gateway->addClient($client, new FakeWebsocketUser(5));

        self::assertSame(5, $gateway->getUserIdByClientId(10));
        self::assertSame([10], $gateway->getClientIdsByUserId(5));
        $gateway->sendText(5, 'hello');
        $gateway->multicastText('team', [5]);
        $gateway->broadcastText('all', [5]);
        self::assertSame($inner, $gateway->getOriginalGateway());

        self::assertIsCallable($closeCallback);
        $closeCallback();
        self::assertNull($gateway->getUserIdByClientId(10));
    }

    public function test_invalid_user_id_is_rejected(): void
    {
        $gateway = new UserAwareWebsocketClientGateway($this->createMock(WebsocketGateway::class));

        $this->expectException(GatewayException::class);

        $gateway->sendText(0, 'bad');
    }
}
