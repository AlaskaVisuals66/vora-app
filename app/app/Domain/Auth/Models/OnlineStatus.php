<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineStatus extends Model
{
    protected $table = 'online_status';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = ['user_id','status','ip_address','socket_id','last_ping_at'];

    protected $casts = [ 'last_ping_at' => 'datetime' ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
