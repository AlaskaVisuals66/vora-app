<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Models\User;
use App\Domain\Sector\Models\Sector;
use App\Domain\Ticket\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $today    = now()->startOfDay();
        $since    = now()->subDays(13)->startOfDay();

        $kpis = [
            'open_tickets'          => Ticket::where('tenant_id', $tenantId)->whereIn('status', ['open','pending'])->count(),
            'queued'                => Ticket::where('tenant_id', $tenantId)->where('status', 'queued')->count(),
            'resolved_today'        => Ticket::where('tenant_id', $tenantId)->where('status', 'closed')->where('closed_at', '>=', $today)->count(),
            'avg_handling_minutes'  => (int) round(((float) Ticket::where('tenant_id', $tenantId)->whereNotNull('resolution_seconds')->avg('resolution_seconds')) / 60),
        ];

        $rows = Ticket::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->groupBy('day')
            ->pluck('total', 'day');

        $timeseries = collect(range(0, 13))->map(function ($i) use ($since, $rows) {
            $d = $since->copy()->addDays($i)->toDateString();
            return ['date' => Carbon::parse($d)->format('d/m'), 'tickets' => (int) ($rows[$d] ?? 0)];
        })->values();

        $sectorCounts = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open','pending','queued'])
            ->whereNotNull('sector_id')
            ->select('sector_id', DB::raw('count(*) as total'))
            ->groupBy('sector_id')
            ->pluck('total', 'sector_id');

        $bySector = Sector::query()
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->withCount(['children', 'attendants'])
            ->orderBy('order')
            ->get(['id','name','color','menu_key','parent_id'])
            ->map(fn($s) => [
                'id'                => $s->id,
                'name'              => $s->name,
                'color'             => $s->color,
                'menu_key'          => $s->menu_key,
                'open_tickets'      => (int) ($sectorCounts[$s->id] ?? 0),
                'attendants_count'  => (int) $s->attendants_count,
                'children_count'    => (int) $s->children_count,
            ])
            ->values();

        $inProgress = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open','pending'])
            ->whereNotNull('assigned_to')
            ->select('assigned_to', DB::raw('count(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $resolvedToday = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'closed')
            ->where('closed_at', '>=', $today)
            ->whereNotNull('assigned_to')
            ->select('assigned_to', DB::raw('count(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $onlineThreshold = now()->subMinutes(5);

        $byAttendant = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id','name','email','last_seen_at'])
            ->map(fn($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'role'        => optional($u->roles->first())->name ?? 'attendant',
                'in_progress' => (int) ($inProgress[$u->id] ?? 0),
                'resolved'    => (int) ($resolvedToday[$u->id] ?? 0),
                'status'      => ($u->last_seen_at && $u->last_seen_at->gte($onlineThreshold)) ? 'online' : 'offline',
            ])
            ->values();

        return response()->json([
            'data' => [
                'kpis'         => $kpis,
                'timeseries'   => $timeseries,
                'by_sector'    => $bySector,
                'by_attendant' => $byAttendant,
            ],
        ]);
    }
}
