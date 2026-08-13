<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MentalFitnessCatalogUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ?string $language,
        public string $reason,
        public string $token,
        public string $updatedAt,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel('mental-fitness.catalog')];
    }

    public function broadcastAs(): string
    {
        return 'tts.catalog.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'language' => $this->language,
            'reason' => $this->reason,
            'token' => $this->token,
            'updated_at' => $this->updatedAt,
        ];
    }
}
