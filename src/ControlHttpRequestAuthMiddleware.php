<?php declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Psr\Log\LoggerInterface;
use webnarmin\AmphpWS\Contracts\Authenticator;

class ControlHttpRequestAuthMiddleware implements Middleware 
{

    private Authenticator $authenticator;
    private LoggerInterface $logger;

    public function __construct(Authenticator $authenticator, LoggerInterface $logger) 
    {
        $this->authenticator = $authenticator;
        $this->logger = $logger;
    }

    public function handleRequest(Request $request, RequestHandler $next): Response
    {
        if($request->getMethod() !== 'POST') {
            $response = $next->handleRequest($request);
            return $response;
        }

        $this->logger->info('Received control HTTP request');

        if (false === $this->authenticator->authenticateControlHttp($request)) {
            $this->logger->warning('Unauthorized control HTTP attempt');
            return new Response(401, [], 'Unauthorized');
        }

        $response = $next->handleRequest($request);
        
        return $response;
    }

}