<?php

namespace App\Domain\Ticket\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappSession extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'tenant_id','instance_name','display_name','phone_number','state',
        'qr_code','webhook_events','connected_at','last_event_at','is_primary',
    ];

    protected $casts = [
        'webhook_events' => 'array',
        'connected_at'   => 'datetime',
        'last_event_at'  => 'datetime',
        'is_primary'     => 'bool',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(\App\Domain\Tenancy\Models\Tenant::class); }
}
