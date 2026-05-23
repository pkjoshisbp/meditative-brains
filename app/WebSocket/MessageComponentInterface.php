<?php

namespace App\WebSocket;

interface MessageComponentInterface
{
    public function onOpen(ConnectionInterface $conn): void;

    public function onClose(ConnectionInterface $conn): void;

    public function onError(ConnectionInterface $conn, \Exception $e): void;

    public function onMessage(ConnectionInterface $from, $msg): void;
}
