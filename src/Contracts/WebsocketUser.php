<?php declare(strict_types=1);

namespace webnarmin\AmphpWS\Contracts;

interface WebsocketUser
{
    public function getId(): ?int;
}