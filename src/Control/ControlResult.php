<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Control;

use Psr\Http\Message\ResponseInterface;

final class ControlResult
{
    private function __construct(
        private readonly bool $success,
        private readonly ?int $statusCode = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $message = null,
    ) {
    }

    public static function success(int $statusCode): self
    {
        return new self(true, $statusCode);
    }

    public static function failure(?int $statusCode, string $errorCode, string $message): self
    {
        return new self(false, $statusCode, $errorCode, $message);
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if ($statusCode >= 200 && $statusCode < 300) {
            return self::success($statusCode);
        }

        if (is_array($decoded) && is_array($decoded['error'] ?? null)) {
            $error = $decoded['error'];

            return self::failure(
                $statusCode,
                is_string($error['code'] ?? null) ? $error['code'] : 'control_request_failed',
                is_string($error['message'] ?? null) ? $error['message'] : 'Control request failed.',
            );
        }

        return self::failure($statusCode, 'control_request_failed', 'Control request failed.');
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
