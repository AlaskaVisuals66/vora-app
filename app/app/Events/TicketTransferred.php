<?php

namespace App\Events;

use App\Domain\Ticket\Models\Ticket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketTransferred implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    public function broadcastOn(): array
    {
        return [ new PrivateChannel("tenant.{$this->ticket->tenant_id}") ];
    }

    public function broadcastAs(): string { return 'ticket.transferred'; }
}
