<?php

use Amp\Http\Server\Driver\Client;
use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\Simple\SimpleAuthenticator;
use webnarmin\Cryptor\Cryptor;
use Amp\Http\Server\Request;
use Psr\Http\Message\UriInterface;

class SimpleAuthenticatorTest extends TestCase
{
    private SimpleAuthenticator $authenticator;
    private Cryptor $cryptor;

    protected function setUp(): void
    {
        $this->cryptor = new Cryptor('test-key');
        $this->authenticator = new SimpleAuthenticator('test-token', $this->cryptor);
    }

    public function testAuthenticateControlHttp()
    {
        $client = $this->createMock(Client::class);
        $uri = $this->createMock(UriInterface::class);
        $request = new Request($client, "POST", $uri, ["authorization" => ["test-token"]]);

        $this->assertTrue($this->authenticator->authenticateControlHttp($request));

        $request = new Request($client, "POST", $uri, ["authorization" => ["wrong-token"]]);

        $this->assertFalse($this->authenticator->authenticateControlHttp($request));
    }

    public function testAuthenticateWebSocket()
    {
        $userId = 123;
        $publicKey = 'test-public-key';
        $token = $this->cryptor->encrypt($userId, $publicKey);

        $client = $this->createMock(Client::class);
        $uri = League\Uri\Http::new('/ws?token=' . urlencode($token). '&publicKey=' . urlencode($publicKey));
        $request = new Request($client, "POST", $uri, []);

        $user = $this->authenticator->authenticateWebSocket($request);

        $this->assertNotNull($user);
        $this->assertEquals($userId, $user->getId());

        // Test with invalid token
        $uri = League\Uri\Http::new("/test?token=invalid-token&publicKey=" . urlencode($publicKey));
        $request = new Request($client, "GET", $uri, []);

        $user = $this->authenticator->authenticateWebSocket($request);

        $this->assertNull($user);
    }
}