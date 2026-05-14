<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AttendantTyping implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public int $tenantId,
        public int $ticketId,
        public int $userId,
        public bool $typing,
    ) {}

    public function broadcastOn(): array
    {
        return [ new PrivateChannel("tenant.{$this->tenantId}.ticket.{$this->ticketId}") ];
    }

    public function broadcastAs(): string { return 'attendant.typing'; }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId, 'typing' => $this->typing];
    }
}
