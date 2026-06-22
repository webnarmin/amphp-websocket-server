<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Control;

use webnarmin\AmphpWS\Exception\ControlHttpException;

final class ControlHttpRequestValidator
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function validate(string $operation, array $data): array
    {
        return match ($operation) {
            'send-text' => [
                'userId' => $this->readUserId($data, 'userId'),
                'payload' => $this->readString($data, 'payload'),
            ],
            'broadcast-text' => [
                'payload' => $this->readString($data, 'payload'),
                'excludedUserIds' => $this->readUserIds($data, 'excludedUserIds', required: false),
            ],
            'broadcast-binary' => [
                'payload' => $this->readBase64Payload($data),
                'excludedUserIds' => $this->readUserIds($data, 'excludedUserIds', required: false),
            ],
            'multicast-text' => [
                'payload' => $this->readString($data, 'payload'),
                'userIds' => $this->readUserIds($data, 'userIds', required: true),
            ],
            'multicast-binary' => [
                'payload' => $this->readBase64Payload($data),
                'userIds' => $this->readUserIds($data, 'userIds', required: true),
            ],
            default => throw new ControlHttpException(
                'invalid_control_request',
                "Unsupported control operation '{$operation}'.",
                404,
            ),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new ControlHttpException('invalid_control_request', "{$key} must be a string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readUserId(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new ControlHttpException('invalid_control_request', "{$key} must be a positive integer.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<int>
     */
    private function readUserIds(array $data, string $key, bool $required): array
    {
        $value = $data[$key] ?? [];
        if ($required && !array_key_exists($key, $data)) {
            throw new ControlHttpException('invalid_control_request', "{$key} is required.");
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new ControlHttpException('invalid_control_request', "{$key} must be a list of positive integers.");
        }

        foreach ($value as $userId) {
            if (!is_int($userId) || $userId <= 0) {
                throw new ControlHttpException('invalid_control_request', "{$key} must contain only positive integers.");
            }
        }

        /** @var list<int> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readBase64Payload(array $data): string
    {
        $encoded = $this->readString($data, 'payload');
        $decoded = base64_decode($encoded, strict: true);
        if ($decoded === false) {
            throw new ControlHttpException('invalid_control_request', 'payload must be valid base64.');
        }

        return $decoded;
    }
}
