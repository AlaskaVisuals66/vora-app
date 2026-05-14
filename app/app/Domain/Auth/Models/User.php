<?php

namespace App\Domain\Auth\Models;

use App\Domain\Sector\Models\Sector;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'tenant_id','name','email','password','avatar_path','phone','status','is_active','last_seen_at',
    ];

    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'is_active'         => 'bool',
            'password'          => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed { return $this->getKey(); }

    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'roles'     => $this->getRoleNames()->toArray(),
            'name'      => $this->name,
        ];
    }

    public function tenant(): BelongsTo  { return $this->belongsTo(Tenant::class); }
    public function sectors(): BelongsToMany { return $this->belongsToMany(Sector::class, 'attendant_sectors')->withPivot(['is_default','priority'])->withTimestamps(); }
    public function tickets(): HasMany   { return $this->hasMany(Ticket::class, 'assigned_to'); }
    public function presence(): HasOne   { return $this->hasOne(\App\Domain\Auth\Models\OnlineStatus::class); }

    public function isAdmin(): bool      { return $this->hasRole('admin'); }
    public function isSupervisor(): bool { return $this->hasRole('supervisor'); }
    public function isAttendant(): bool  { return $this->hasRole('attendant'); }
}
