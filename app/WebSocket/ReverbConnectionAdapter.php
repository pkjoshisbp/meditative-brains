<?php

namespace App\WebSocket;

use Laravel\Reverb\Servers\Reverb\Connection as ReverbWebSocketConnection;

class ReverbConnectionAdapter extends ConnectionInterface
{
    public function __construct(private readonly ReverbWebSocketConnection $connection)
    {
        parent::__construct($connection->id());
    }

    public function send(string $payload): void
    {
        $this->connection->send($payload);
    }

    public function close(): void
    {
        $this->connection->close();
    }
}
