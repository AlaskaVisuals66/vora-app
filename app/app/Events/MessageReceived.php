<?php

namespace App\Events;

use App\Domain\Message\Models\Message;
use Illuminate\Broadcasting\Channel;
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
        return [
            'id'        => $this->message->id,
            'ticket_id' => $this->message->ticket_id,
            'direction' => $this->message->direction,
            'type'      => $this->message->type,
            'body'      => $this->message->body,
            'created_at'=> $this->message->created_at?->toIso8601String(),
        ];
    }
}
