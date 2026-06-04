<?php

namespace App\Domain\Channel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'type', 'name', 'config', 'identifier',
        'is_active', 'is_primary', 'last_used_at',
    ];

    protected $casts = [
        'config'      => 'array',
        'is_active'   => 'bool',
        'is_primary'  => 'bool',
        'last_used_at'=> 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Tenancy\Models\Tenant::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByType($q, string $type)
    {
        return $q->where('type', $type);
    }
}
