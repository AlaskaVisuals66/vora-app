<?php

namespace App\Domain\Client\Models;

use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id','name','phone','whatsapp_jid','email','document','avatar_url','tags','attributes','notes','last_message_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'attributes' => 'array',
        'last_message_at' => 'datetime',
    ];

    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
    public function activeTicket(): ?Ticket
    {
        return $this->tickets()->whereIn('status', ['menu','queued','open','pending'])->latest('id')->first();
    }
}
