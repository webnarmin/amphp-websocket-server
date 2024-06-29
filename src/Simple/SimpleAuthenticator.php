<?php declare(strict_types=1);

namespace webnarmin\AmphpWS\Simple;

use Amp\Http\Server\Request;
use webnarmin\AmphpWS\Contracts\Authenticator;
use webnarmin\AmphpWS\Contracts\WebsocketUser;
use webnarmin\Cryptor\Cryptor;

class SimpleAuthenticator implements Authenticator
{
    private string $httpAuthToken;
    private Cryptor $cryptor;

    public function __construct(string $httpAuthToken, Cryptor $cryptor)
    {
        $this->httpAuthToken = $httpAuthToken;
        $this->cryptor = $cryptor;
    }

    public function authenticateControlHttp(Request $request): bool
    {
        $token = $request->getHeader('Authorization');

        $isAuthenticated = $token === $this->httpAuthToken;
        return $isAuthenticated;
    }

    public function authenticateWebSocket(Request $request): ?WebsocketUser
    {
        $token = (string)$request->getQueryParameter('token');
        $publicKey = (string)$request->getQueryParameter('publicKey');

        if(!$token || !$publicKey) return null;

        try {
            $decrypted = $this->cryptor->decrypt($token, $publicKey);
        } catch(\RuntimeException $e) {
            return null;
        }

        $isAuthenticated = is_numeric($decrypted) && (int)$decrypted > 0;
        if (!$isAuthenticated) {
            return null;
        }

        $userId = (int)$decrypted;
        return new SimpleWebsocketUser($userId);
    }
}