<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Psr\Log\LoggerInterface;
use webnarmin\AmphpWS\Contracts\Authenticator;

class ControlHttpRequestAuthMiddleware implements Middleware
{
    public function __construct(
        private Authenticator $authenticator,
        private LoggerInterface $logger,
    ) {
    }

    public function handleRequest(Request $request, RequestHandler $next): Response
    {
        if ($request->getMethod() !== 'POST') {
            return $next->handleRequest($request);
        }

        $this->logger->info('Received control HTTP request');

        if (false === $this->authenticator->authenticateControlHttp($request)) {
            $this->logger->warning('Unauthorized control HTTP attempt');

            return new Response(401, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'error',
                'error' => [
                    'code' => 'authentication_failed',
                    'message' => 'Unauthorized control HTTP request.',
                ],
            ], JSON_THROW_ON_ERROR));
        }

        return $next->handleRequest($request);
    }
}
