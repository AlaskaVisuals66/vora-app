<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'url'  => $this->url,
            'mime' => $this->mime_type,
            'name' => $this->original_name,
            'size' => $this->size_bytes,
        ];
    }
}
