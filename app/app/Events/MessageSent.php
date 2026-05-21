<?php

namespace App\Events;

use App\Domain\Message\Models\Message;
use App\Http\Resources\MessageResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->message->tenant_id}"),
            new PrivateChannel("tenant.{$this->message->tenant_id}.ticket.{$this->message->ticket_id}"),
        ];
    }

    public function broadcastAs(): string { return 'message.sent'; }

    public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
