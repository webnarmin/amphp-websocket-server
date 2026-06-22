<?php

declare(strict_types=1);

namespace webnarmin\AmphpWS\Simple;

use webnarmin\AmphpWS\Contracts\WebsocketUser;

class SimpleWebsocketUser implements WebsocketUser
{
    public function __construct(private int $userId)
    {
    }

    public function getId(): int
    {
        return $this->userId;
    }
}
