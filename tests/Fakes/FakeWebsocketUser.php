<?php

declare(strict_types=1);

namespace webnarmin\AmphpWSTest\Fakes;

use webnarmin\AmphpWS\Contracts\WebsocketUser;

final class FakeWebsocketUser implements WebsocketUser
{
    public function __construct(private int $id = 123)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
