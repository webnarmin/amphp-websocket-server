<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Protocol;

use JsonException;
use webnarmin\AmphpWS\Exception\ProtocolException;

final class MessageEnvelope
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $action,
        private readonly array $payload = [],
        private readonly ?string $requestId = null,
        private readonly array $metadata = [],
    ) {
        if (trim($this->action) === '') {
            throw new ProtocolException('invalid_message', 'Message action must be a non-empty string.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $action = $data['action'] ?? null;
        if (!is_string($action) || trim($action) === '') {
            throw new ProtocolException('invalid_message', 'Message action must be a non-empty string.');
        }

        $payload = $data['payload'] ?? [];
        if (!is_array($payload) || ($payload !== [] && array_is_list($payload))) {
            throw new ProtocolException('invalid_message', 'Message payload must be an object.');
        }

        $requestId = $data['requestId'] ?? null;
        if ($requestId !== null && (!is_string($requestId) || $requestId === '')) {
            throw new ProtocolException('invalid_message', 'Message requestId must be a non-empty string when provided.');
        }

        $metadata = $data['metadata'] ?? [];
        if (!is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            throw new ProtocolException('invalid_message', 'Message metadata must be an object.');
        }

        /** @var array<string, mixed> $payload */
        /** @var array<string, mixed> $metadata */
        return new self($action, $payload, $requestId, $metadata);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException('invalid_json', 'Invalid JSON message.', previous: $exception);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new ProtocolException('invalid_message', 'Message must be a JSON object.');
        }

        /** @var array<string, mixed> $data */
        return self::fromArray($data);
    }

    public function getAction(): string
    {
        return $this->action;
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

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'action' => $this->action,
            'payload' => $this->payload,
        ];

        if ($this->requestId !== null) {
            $data['requestId'] = $this->requestId;
        }

        if ($this->metadata !== []) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
