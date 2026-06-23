<?php

namespace App\Events;

use App\Models\FestEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Services\Events\EventContext;

class FestScoreboardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FestEvent $event) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('fest-scoreboard.'.$this->event->tenant_id.'.'.$this->event->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'scoreboard.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id'   => $this->event->id,
            'scoreboard' => EventContext::for($this->event)->scoreboardBySchool(),
        ];
    }
}
