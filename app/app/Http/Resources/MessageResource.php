<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'ticket_id'  => $this->ticket_id,
            'direction'  => $this->direction,
            'type'       => $this->type,
            'body'       => $this->body,
            'media'      => $this->media,
            'status'     => $this->status,
            'sender'     => $this->whenLoaded('sender'),
            'attachments'=> AttachmentResource::collection($this->whenLoaded('attachments')),
            'sent_at'    => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
