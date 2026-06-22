<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Control;

use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\Control\ControlHttpRequestValidator;
use webnarmin\AmphpWS\Exception\ControlHttpException;

final class ControlHttpRequestValidatorTest extends TestCase
{
    public function test_send_text_request_is_validated(): void
    {
        $data = (new ControlHttpRequestValidator())->validate('send-text', [
            'userId' => 5,
            'payload' => 'hello',
        ]);

        self::assertSame(['userId' => 5, 'payload' => 'hello'], $data);
    }

    public function test_binary_payload_must_be_valid_base64(): void
    {
        $this->expectException(ControlHttpException::class);
        $this->expectExceptionMessage('payload must be valid base64.');

        (new ControlHttpRequestValidator())->validate('broadcast-binary', [
            'payload' => 'not-base64',
        ]);
    }

    public function test_multicast_requires_user_ids(): void
    {
        $this->expectException(ControlHttpException::class);
        $this->expectExceptionMessage('userIds is required.');

        (new ControlHttpRequestValidator())->validate('multicast-text', [
            'payload' => 'hello',
        ]);
    }
}
