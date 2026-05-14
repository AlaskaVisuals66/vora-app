<?php

use App\Domain\Auth\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

// Per-tenant private channel — only members of the tenant
Broadcast::channel('tenant.{tenantId}', function (User $user, int $tenantId) {
    return $user->tenant_id === $tenantId;
});

// Sector room — supervisors/admins of that tenant + attendants of that sector
Broadcast::channel('tenant.{tenantId}.sector.{sectorId}', function (User $user, int $tenantId, int $sectorId) {
    if ($user->tenant_id !== $tenantId) return false;
    if ($user->isAdmin() || $user->isSupervisor()) return true;
    return $user->sectors()->whereKey($sectorId)->exists();
});

// Ticket conversation — assignee + admins + supervisors of the sector
Broadcast::channel('tenant.{tenantId}.ticket.{ticketId}', function (User $user, int $tenantId, int $ticketId) {
    if ($user->tenant_id !== $tenantId) return false;
    $ticket = Ticket::query()->where('tenant_id', $tenantId)->find($ticketId);
    if (! $ticket) return false;
    return $user->isAdmin()
        || $user->isSupervisor()
        || $ticket->assigned_to === $user->id;
});

// Presence channel for online users dashboard
Broadcast::channel('presence-tenant.{tenantId}', function (User $user, int $tenantId) {
    if ($user->tenant_id !== $tenantId) return false;
    return [
        'id'     => $user->id,
        'name'   => $user->name,
        'role'   => $user->getRoleNames()->first(),
        'avatar' => $user->avatar_path,
    ];
});
