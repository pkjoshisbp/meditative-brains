<?php

namespace App\WebSocket;

abstract class ConnectionInterface
{
    public function __construct(public readonly int|string $resourceId)
    {
    }

    abstract public function send(string $payload): void;

    abstract public function close(): void;
}
