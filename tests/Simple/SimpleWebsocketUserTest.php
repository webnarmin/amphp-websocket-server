<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Simple;

use PHPUnit\Framework\TestCase;
use webnarmin\AmphpWS\Simple\SimpleWebsocketUser;

class SimpleWebsocketUserTest extends TestCase
{
    public function testConstructorWithUserId(): void
    {
        $user = new SimpleWebsocketUser(123);
        $this->assertSame(123, $user->getId());
    }

    public function testConstructorWithNullUserId(): void
    {
        $user = new SimpleWebsocketUser(null);
        $this->assertNull($user->getId());
    }

    public function testGetId(): void
    {
        $userId = 456;
        $user = new SimpleWebsocketUser($userId);
        $this->assertSame($userId, $user->getId());
    }
}
