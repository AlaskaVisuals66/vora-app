<?php

namespace App\Events;

use App\Domain\Message\Models\Message;
use App\Http\Resources\MessageResource;
use App\Http\Resources\TicketResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->message->tenant_id}.ticket.{$this->message->ticket_id}"),
            new PrivateChannel("tenant.{$this->message->tenant_id}"),
        ];
    }

    public function broadcastAs(): string { return 'message.received'; }

    public function broadcastWith(): array
    {
        $ticket = $this->message->ticket()->with(['sector', 'assignee', 'client'])->first();

        return [
            'message' => (new MessageResource($this->message))->resolve(),
            'ticket'  => $ticket ? (new TicketResource($ticket))->resolve() : null,
        ];
    }
}
