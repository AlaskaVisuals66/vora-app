<?php

namespace App\Domain\Ticket\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Client\Models\Client;
use App\Domain\Message\Models\Message;
use App\Domain\Sector\Models\Sector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id','protocol','client_id','sector_id','assigned_to','whatsapp_session_id',
        'status','priority','channel','subject','menu_state',
        'first_response_seconds','resolution_seconds','messages_count',
        'queued_at','assigned_at','first_response_at','last_message_at','closed_at',
    ];

    protected $casts = [
        'menu_state'         => 'array',
        'queued_at'          => 'datetime',
        'assigned_at'        => 'datetime',
        'first_response_at'  => 'datetime',
        'last_message_at'    => 'datetime',
        'closed_at'          => 'datetime',
    ];

    public function client(): BelongsTo  { return $this->belongsTo(Client::class); }
    public function sector(): BelongsTo  { return $this->belongsTo(Sector::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages(): HasMany  { return $this->hasMany(Message::class); }
    public function transfers(): HasMany { return $this->hasMany(TicketTransfer::class); }
    public function tags(): BelongsToMany { return $this->belongsToMany(TicketTag::class, 'ticket_tag_pivot'); }

    public function isOpen(): bool   { return in_array($this->status, ['open','pending'], true); }
    public function isClosed(): bool { return in_array($this->status, ['resolved','closed'], true); }
}
