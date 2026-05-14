<?php

namespace App\Events;

use App\Domain\Ticket\Models\Ticket;
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
        return [
            'id'         => $this->ticket->id,
            'protocol'   => $this->ticket->protocol,
            'sector_id'  => $this->ticket->sector_id,
            'queued_at'  => $this->ticket->queued_at?->toIso8601String(),
        ];
    }
}
