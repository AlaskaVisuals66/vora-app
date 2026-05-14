<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'protocol'    => $this->protocol,
            'status'      => $this->status,
            'priority'    => $this->priority,
            'subject'     => $this->subject,
            'sector'      => $this->whenLoaded('sector'),
            'assignee'    => $this->whenLoaded('assignee'),
            'client'      => $this->whenLoaded('client'),
            'tags'        => $this->whenLoaded('tags'),
            'messages_count' => $this->messages_count,
            'queued_at'   => $this->queued_at?->toIso8601String(),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
