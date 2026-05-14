<?php

namespace App\Domain\Ticket\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTransfer extends Model
{
    protected $fillable = [
        'ticket_id','from_user_id','to_user_id','from_sector_id','to_sector_id','reason','transferred_at',
    ];

    protected $casts = [ 'transferred_at' => 'datetime' ];

    public function ticket(): BelongsTo    { return $this->belongsTo(Ticket::class); }
    public function fromUser(): BelongsTo  { return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'from_user_id'); }
    public function toUser(): BelongsTo    { return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'to_user_id'); }
}
