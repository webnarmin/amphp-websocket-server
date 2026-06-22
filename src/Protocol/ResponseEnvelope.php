<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Protocol;

use JsonException;
use webnarmin\AmphpWS\Exception\ProtocolException;

final class ResponseEnvelope
{
    /**
     * @param array<string, mixed> $payload
     * @param array{code: string, message: string}|null $error
     */
    private function __construct(
        private readonly string $status,
        private readonly array $payload = [],
        private readonly ?string $requestId = null,
        private readonly ?array $error = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function success(array $payload = [], ?string $requestId = null): self
    {
        return new self('success', $payload, $requestId);
    }

    public static function error(string $code, string $message, ?string $requestId = null): self
    {
        return new self('error', [], $requestId, [
            'code' => $code,
            'message' => $message,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;
        if ($status !== 'success' && $status !== 'error') {
            throw new ProtocolException('invalid_message', 'Response status must be success or error.');
        }

        $payload = $data['payload'] ?? [];
        if (!is_array($payload) || ($payload !== [] && array_is_list($payload))) {
            throw new ProtocolException('invalid_message', 'Response payload must be an object.');
        }

        $requestId = $data['requestId'] ?? null;
        if ($requestId !== null && (!is_string($requestId) || $requestId === '')) {
            throw new ProtocolException('invalid_message', 'Response requestId must be a non-empty string when provided.');
        }

        if ($status === 'success') {
            /** @var array<string, mixed> $payload */
            return self::success($payload, $requestId);
        }

        $error = $data['error'] ?? null;
        if (!is_array($error) || !is_string($error['code'] ?? null) || !is_string($error['message'] ?? null)) {
            throw new ProtocolException('invalid_message', 'Error response must contain error code and message.');
        }

        return self::error($error['code'], $error['message'], $requestId);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException('invalid_json', 'Invalid JSON response.', previous: $exception);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new ProtocolException('invalid_message', 'Response must be a JSON object.');
        }

        /** @var array<string, mixed> $data */
        return self::fromArray($data);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function withRequestId(?string $requestId): self
    {
        if ($this->error !== null) {
            return self::error($this->error['code'], $this->error['message'], $requestId);
        }

        return self::success($this->payload, $requestId);
    }

    /**
     * @return array{code: string, message: string}|null
     */
    public function getError(): ?array
    {
        return $this->error;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
            'payload' => $this->payload,
        ];

        if ($this->requestId !== null) {
            $data['requestId'] = $this->requestId;
        }

        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
