<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public function log(?User $user, string $action, Request $request, array $context = [], ?string $subjectType = null, ?int $subjectId = null): AuditLog
    {
        return AuditLog::create([
            'tenant_id'    => $user?->tenant_id,
            'user_id'      => $user?->id,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'context'      => $context,
            'ip_address'   => $request->ip(),
            'user_agent'   => substr((string) $request->userAgent(), 0, 1024),
        ]);
    }
}
