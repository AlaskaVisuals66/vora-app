<?php

namespace App\Domain\Message\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = [
        'tenant_id','message_id','disk','path','mime_type','original_name','size_bytes','metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'size_bytes' => 'int',
    ];

    public function message(): BelongsTo { return $this->belongsTo(Message::class); }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
