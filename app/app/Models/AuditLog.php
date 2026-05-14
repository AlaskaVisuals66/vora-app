<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'tenant_id','user_id','action','subject_type','subject_id','context','ip_address','user_agent',
    ];

    protected $casts = [ 'context' => 'array' ];
}
