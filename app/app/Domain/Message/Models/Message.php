<?php

namespace App\Domain\Message\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id','ticket_id','sender_user_id','external_id','direction','type',
        'body','media','metadata','status','failure_reason',
        'sent_at','delivered_at','read_at',
    ];

    protected $casts = [
        'media'        => 'array',
        'metadata'     => 'array',
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
    ];

    public function ticket(): BelongsTo  { return $this->belongsTo(Ticket::class); }
    public function sender(): BelongsTo  { return $this->belongsTo(User::class, 'sender_user_id'); }
    public function attachments(): HasMany { return $this->hasMany(Attachment::class); }
}
