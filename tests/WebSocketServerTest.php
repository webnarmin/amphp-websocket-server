<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Amp\Websocket\WebsocketClient;
use webnarmin\AmphpWS\Configurator;
use webnarmin\AmphpWS\Contracts\Authenticator;
use webnarmin\AmphpWS\WebSocketServer;
use webnarmin\AmphpWS\UserAwareWebsocketClientGateway;
use Psr\Log\LoggerInterface;

class WebSocketServerTest extends TestCase
{
    private $configuratorMock;
    private $authenticatorMock;
    private $loggerMock;
    private $webSocketServer;
    private $gatewayMock;

    protected function setUp(): void
    {
        $this->configuratorMock = $this->createMock(Configurator::class);
        $this->authenticatorMock = $this->createMock(Authenticator::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->gatewayMock = $this->createMock(UserAwareWebsocketClientGateway::class);

        $this->webSocketServer = $this->getMockBuilder(WebSocketServer::class)
            ->setConstructorArgs([$this->configuratorMock, $this->authenticatorMock, $this->loggerMock])
            ->onlyMethods(['processClientMessages'])
            ->getMockForAbstractClass();

        $reflectionProperty = new \ReflectionProperty(WebSocketServer::class, 'gateway');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->webSocketServer, $this->gatewayMock);
    }

    public function testIsValidMessage()
    {
        $validMessage = ['action' => 'test', 'payload' => []];
        $invalidMessage1 = ['action' => 'test'];
        $invalidMessage2 = ['payload' => []];
        $invalidMessage3 = null;

        $reflectionMethod = new \ReflectionMethod($this->webSocketServer, 'isValidMessage');
        $reflectionMethod->setAccessible(true);

        $this->assertTrue($reflectionMethod->invoke($this->webSocketServer, $validMessage));
        $this->assertFalse($reflectionMethod->invoke($this->webSocketServer, $invalidMessage1));
        $this->assertFalse($reflectionMethod->invoke($this->webSocketServer, $invalidMessage2));
        $this->assertFalse($reflectionMethod->invoke($this->webSocketServer, $invalidMessage3));
    }

    public function testProcessClientMessagesWithException()
    {
        $clientMock = $this->createMock(WebsocketClient::class);
        $clientMock->method('getId')->willReturn(1);

        $clientMock->method('receive')
            ->willThrowException(new \Exception('Test exception'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error processing messages for client. ID: 1'));

        $reflectionMethod = new \ReflectionMethod($this->webSocketServer, 'processClientMessages');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->webSocketServer, $clientMock);
    }
}