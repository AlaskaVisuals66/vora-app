<?php

namespace App\Domain\Ticket\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TicketTag extends Model
{
    protected $fillable = ['tenant_id','name','color'];

    public function tickets(): BelongsToMany { return $this->belongsToMany(Ticket::class, 'ticket_tag_pivot'); }
}
