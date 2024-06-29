<?php

use Amp\ByteStream\ReadableBuffer;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Driver\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use webnarmin\AmphpWS\Contracts\Authenticator;
use webnarmin\AmphpWS\ControlHttpRequestAuthMiddleware;

class ControlHttpRequestAuthMiddlewareTest extends TestCase
{
    public function testHandleRequestWithPostAndValidToken()
    {
        $authenticator = $this->createMock(Authenticator::class);
        $authenticator->method('authenticateControlHttp')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);

        $middleware = new ControlHttpRequestAuthMiddleware($authenticator, $logger);

        $client = $this->createMock(Client::class);
        $uri = $this->createMock(UriInterface::class);
        $request = new Request($client, "POST", $uri);
        $request->setHeader('Authorization', 'valid-token');

        $nextHandler = $this->createMock(RequestHandler::class);
        $nextHandler->method('handleRequest')->willReturn(new Response(200, [], new ReadableBuffer('OK')));

        $response = $middleware->handleRequest($request, $nextHandler);

        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('OK', $response->getBody()->read());
    }

}