<?php

namespace App\Domain\Sector\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id','parent_id','name','slug','menu_key','color','icon','description','working_hours','settings','ai_settings','active','order',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'settings'      => 'array',
        'ai_settings'   => 'array',
        'active' => 'bool',
        'order'  => 'int',
    ];

    public function parent(): BelongsTo   { return $this->belongsTo(Sector::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(Sector::class, 'parent_id')->orderBy('order'); }
    public function attendants(): BelongsToMany { return $this->belongsToMany(User::class, 'attendant_sectors')->withPivot(['is_default','priority'])->withTimestamps(); }

    public function isLeaf(): bool { return $this->children()->count() === 0; }
}
