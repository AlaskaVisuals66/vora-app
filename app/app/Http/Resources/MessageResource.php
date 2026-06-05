<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $effectiveAt = $this->sent_at ?? $this->delivered_at ?? $this->created_at;

        return [
            'id'           => $this->id,
            'ticket_id'    => $this->ticket_id,
            'direction'    => $this->direction,
            'type'         => $this->type,
            'body'         => $this->body,
            // Only expose display-safe media fields — never the raw Evolution blob
            // (mediaKey, directPath, encryption metadata). Bytes go via /media.
            'media'        => is_array($this->media) ? array_filter([
                'mimetype' => $this->media['mimetype'] ?? null,
                'fileName' => $this->media['fileName'] ?? ($this->media['filename'] ?? null),
                'caption'  => $this->media['caption'] ?? null,
                'seconds'  => $this->media['seconds'] ?? null,
            ], fn ($v) => $v !== null) : null,
            'status'       => $this->status,
            'sender'       => $this->whenLoaded('sender'),
            'attachments'  => AttachmentResource::collection($this->whenLoaded('attachments')),
            'sent_at'      => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'timestamp'    => $effectiveAt?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
