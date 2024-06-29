<?php declare(strict_types=1);

use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\WebsocketClient;
use Amp\Future;
use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\Contracts\WebsocketUser;
use webnarmin\AmphpWS\UserAwareWebsocketClientGateway;

class UserAwareWebsocketClientGatewayTest extends TestCase
{
    private WebsocketClientGateway $gateway;
    private $clientMock;
    private $userMock;
    private $clientId = 1;
    private $userId = 123;
    private UserAwareWebsocketClientGateway $userAwareGateway;

    protected function setUp(): void
    {
        $this->gateway = new WebsocketClientGateway();
        $this->clientMock = $this->createMock(WebsocketClient::class);
        $this->userMock = $this->createMock(WebsocketUser::class);

        $this->clientMock->method('getId')->willReturn($this->clientId);
        $this->userMock->method('getId')->willReturn($this->userId);

        $this->userAwareGateway = new UserAwareWebsocketClientGateway($this->gateway);
    }

    public function testAddClient(): void
    {
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $this->assertSame($this->userId, $this->userAwareGateway->getUserIdByClientId($this->clientId));
        $this->assertSame($this->userMock, $this->userAwareGateway->getUserByClientId($this->clientId));
    }

    public function testGetUserIdByClientId(): void
    {
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $result = $this->userAwareGateway->getUserIdByClientId($this->clientId);
        $this->assertSame($this->userId, $result);
    }

    public function testGetUserByClientId(): void
    {
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $result = $this->userAwareGateway->getUserByClientId($this->clientId);
        $this->assertSame($this->userMock, $result);
    }

    public function testGetClientIdsByUserId(): void
    {
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $result = $this->userAwareGateway->getClientIdsByUserId($this->userId);
        $this->assertSame([$this->clientId], $result);
    }

    public function testBroadcastText(): void
    {
        $data = 'test message';
        $result = $this->userAwareGateway->broadcastText($data);
        $this->assertInstanceOf(Future::class, $result);
    }

    public function testBroadcastBinary(): void
    {
        $data = 'binary data';
        $result = $this->userAwareGateway->broadcastBinary($data);
        $this->assertInstanceOf(Future::class, $result);
    }

    public function testMulticastText(): void
    {
        $data = 'multicast message';
        $userIds = [$this->userId];
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $result = $this->userAwareGateway->multicastText($data, $userIds);
        $this->assertInstanceOf(Future::class, $result);
    }

    public function testMulticastBinary(): void
    {
        $data = 'binary multicast data';
        $userIds = [$this->userId];
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $result = $this->userAwareGateway->multicastBinary($data, $userIds);
        $this->assertInstanceOf(Future::class, $result);
    }

    public function testSendText(): void
    {
        $data = 'individual message';
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $result = $this->userAwareGateway->sendText($data, $this->userId);
        $this->assertInstanceOf(Future::class, $result);
    }

    public function testSendBinary(): void
    {
        $data = 'individual binary data';
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $result = $this->userAwareGateway->sendBinary($data, $this->userId);
        $this->assertInstanceOf(Future::class, $result);
    }

    public function testGetClients(): void
    {
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        $result = $this->userAwareGateway->getClients();
        $this->assertIsArray($result);
    }

    public function testGetOriginalGateway(): void
    {
        $result = $this->userAwareGateway->getOrignalGateway();
        $this->assertInstanceOf(WebsocketClientGateway::class, $result);
        $this->assertSame($this->gateway, $result);
    }

    public function testClientRemoval(): void
    {
        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);

        // Simulate client disconnection
        $closeCallback = null;
        $this->clientMock->method('onClose')->willReturnCallback(function ($callback) use (&$closeCallback) {
            $closeCallback = $callback;
        });

        $this->userAwareGateway->addClient($this->clientMock, $this->userMock);
        $this->assertNotNull($closeCallback);

        // Trigger the close callback
        $closeCallback();

        $this->assertNull($this->userAwareGateway->getUserIdByClientId($this->clientId));
        $this->assertNull($this->userAwareGateway->getUserByClientId($this->clientId));
        $this->assertEmpty($this->userAwareGateway->getClientIdsByUserId($this->userId));
    }
}