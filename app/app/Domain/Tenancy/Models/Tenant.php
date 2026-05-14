<?php

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name','slug','document','plan','settings','active'];

    protected $casts = [
        'settings' => 'array',
        'active'   => 'bool',
    ];

    public function users(): HasMany     { return $this->hasMany(\App\Domain\Auth\Models\User::class); }
    public function sectors(): HasMany   { return $this->hasMany(\App\Domain\Sector\Models\Sector::class); }
    public function clients(): HasMany   { return $this->hasMany(\App\Domain\Client\Models\Client::class); }
    public function tickets(): HasMany   { return $this->hasMany(\App\Domain\Ticket\Models\Ticket::class); }
}
