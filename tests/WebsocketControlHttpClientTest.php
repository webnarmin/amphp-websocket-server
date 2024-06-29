<?php declare(strict_types=1);

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\WebsocketControlHttpClient;
use Psr\Log\LoggerInterface;

class WebsocketControlHttpClientTest extends TestCase
{
    private $clientMock;
    private $loggerMock;
    private $httpClient;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->httpClient = new WebsocketControlHttpClient($this->clientMock, $this->loggerMock);
    }

    public function testSendTextSuccess(): void
    {
        $this->clientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/send-text', [
                'json' => [
                    'userId' => 123,
                    'payload' => 'test payload',
                ]
            ])
            ->willReturn(new Response(200));

        $this->loggerMock->expects($this->exactly(2))
            ->method('info');

        $result = $this->httpClient->sendText(123, 'test payload');
        $this->assertTrue($result);
    }

    public function testSendTextFailure(): void
    {
        $this->clientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/send-text', [
                'json' => [
                    'userId' => 123,
                    'payload' => 'test payload',
                ]
            ])
            ->will($this->throwException(new class extends \Exception implements GuzzleException {}));

        $this->loggerMock->expects($this->once())
            ->method('error');

        $result = $this->httpClient->sendText(123, 'test payload');
        $this->assertFalse($result);
    }

    public function testBroadcastTextSuccess(): void
    {
        $this->clientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/broadcast-text', [
                'json' => [
                    'payload' => 'test payload',
                    'excludedUserIds' => [],
                ]
            ])
            ->willReturn(new Response(200));

        $this->loggerMock->expects($this->exactly(2))
            ->method('info');

        $result = $this->httpClient->broadcastText('test payload');
        $this->assertTrue($result);
    }

    public function testBroadcastBinarySuccess(): void
    {
        $this->clientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/broadcast-binary', [
                'json' => [
                    'payload' => base64_encode('test payload'),
                    'excludedUserIds' => [],
                ]
            ])
            ->willReturn(new Response(200));

        $this->loggerMock->expects($this->exactly(2))
            ->method('info');

        $result = $this->httpClient->broadcastBinary('test payload');
        $this->assertTrue($result);
    }

    public function testMulticastTextSuccess(): void
    {
        $this->clientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/multicast-text', [
                'json' => [
                    'payload' => 'test payload',
                    'userIds' => [1, 2, 3],
                ]
            ])
            ->willReturn(new Response(200));

        $this->loggerMock->expects($this->exactly(2))
            ->method('info');

        $result = $this->httpClient->multicastText('test payload', [1, 2, 3]);
        $this->assertTrue($result);
    }

    public function testMulticastBinarySuccess(): void
    {
        $this->clientMock->expects($this->once())
            ->method('request')
            ->with('POST', '/multicast-binary', [
                'json' => [
                    'payload' => base64_encode('test payload'),
                    'userIds' => [1, 2, 3],
                ]
            ])
            ->willReturn(new Response(200));

        $this->loggerMock->expects($this->exactly(2))
            ->method('info');

        $result = $this->httpClient->multicastBinary('test payload', [1, 2, 3]);
        $this->assertTrue($result);
    }
}
