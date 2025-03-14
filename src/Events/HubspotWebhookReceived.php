<?php

namespace Visualbuilder\FilamentHubspot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HubspotWebhookReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public function __construct(public int|string $contactId, public array $eventData)
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
