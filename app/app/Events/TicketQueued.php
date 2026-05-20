<?php

namespace App\Events;

use App\Domain\Ticket\Models\Ticket;
use App\Http\Resources\TicketResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketQueued implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->ticket->tenant_id}"),
            new PrivateChannel("tenant.{$this->ticket->tenant_id}.sector.{$this->ticket->sector_id}"),
        ];
    }

    public function broadcastAs(): string { return 'ticket.queued'; }

    public function broadcastWith(): array
    {
        $this->ticket->loadMissing(['sector', 'assignee', 'client']);

        return [
            'ticket' => (new TicketResource($this->ticket))->resolve(),
        ];
    }
}
