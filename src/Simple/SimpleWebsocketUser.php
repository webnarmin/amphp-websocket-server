<?php declare(strict_types=1);

namespace webnarmin\AmphpWS\Simple;

use webnarmin\AmphpWS\Contracts\WebsocketUser;

class SimpleWebsocketUser implements WebsocketUser
{
    private ?int $userId;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
    }

    public function getId(): ?int
    {
        return $this->userId;
    }

}
