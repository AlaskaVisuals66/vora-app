<?php

namespace App\Domain\Client\Models;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\WhatsappSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /** WhatsApp numbers (instances) this contact belongs to. */
    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappSession::class, 'client_whatsapp_session')
            ->withPivot('name_on_instance')
            ->withTimestamps();
    }
    public function activeTicket(): ?Ticket
    {
        return $this->tickets()->whereIn('status', ['menu','queued','open','pending'])->latest('id')->first();
    }
}
