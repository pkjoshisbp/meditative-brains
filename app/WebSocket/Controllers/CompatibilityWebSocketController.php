<?php

namespace App\WebSocket\Controllers;

use App\WebSocket\ReverbConnectionAdapter;
use App\WebSocket\TtsWebSocketServer;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Servers\Reverb\Connection;
use Psr\Http\Message\RequestInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;

class CompatibilityWebSocketController
{
    public function __construct(
        private readonly TtsWebSocketServer $handler,
        private readonly ApplicationProvider $applications,
    ) {
    }

    public function __invoke(RequestInterface $request, Connection $connection): void
    {
        $application = $this->applications->all()->first();
        $connection->withMaxMessageSize($application?->maxMessageSize() ?? 10_000);

        $adapter = new ReverbConnectionAdapter($connection);

        $connection->onMessage(function ($message) use ($adapter) {
            try {
                $this->handler->onMessage($adapter, (string) $message);
            } catch (\Throwable $e) {
                $this->handler->onError($adapter, $e instanceof \Exception ? $e : new \RuntimeException($e->getMessage(), 0, $e));
            }
        });

        $connection->onControl(function (FrameInterface $message) use ($connection) {
            if ($message->getOpcode() === Frame::OP_PING) {
                $connection->send(new Frame($message->getPayload(), opcode: Frame::OP_PONG));
            }
        });

        $connection->onClose(fn () => $this->handler->onClose($adapter));

        $connection->openBuffer();
        $this->handler->onOpen($adapter);
    }
}
