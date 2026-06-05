<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Client\Models\Client;
use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Services\MenuEngine;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMaintenanceController extends Controller
{
    public function __construct(private readonly MenuEngine $menu) {}

    /**
     * Read-only: list tickets stuck in the menu (the bug parked them there) and
     * show where each WOULD route, by replaying the digits the client already
     * typed through the fixed menu logic. Writes NOTHING (rolled back).
     */
    public function stuckMenus(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $hours    = (int) $request->query('recent_hours', 0);
        $tickets  = $this->stuckMenuTickets($tenantId, $hours);

        $rows = [];
        DB::beginTransaction();
        try {
            foreach ($tickets as $ticket) {
                $res = $this->replayMenu($ticket);
                $rows[] = [
                    'ticket_id'    => $ticket->id,
                    'protocol'     => $ticket->protocol,
                    'client'       => $ticket->client?->name,
                    'phone'        => $ticket->client?->phone,
                    'last_message' => optional($ticket->last_message_at)->toIso8601String(),
                    'inputs'       => $res['inputs'],
                    'would_route'  => $res['sector'],     // null = no valid choice yet
                ];
            }
        } finally {
            DB::rollBack(); // preview only — discard every mutation
        }

        $resolvable = collect($rows)->whereNotNull('would_route')->count();

        return response()->json([
            'total'      => count($rows),
            'resolvable' => $resolvable,
            'incomplete' => count($rows) - $resolvable,
            'matches'    => $rows,
        ]);
    }

    /**
     * Apply: silently route stuck-menu tickets to the sector the client already
     * chose. Sets sector_id + queues them — NO message is sent to the client,
     * no n8n/broadcast side effects. Pass recent_hours to limit the scope.
     */
    public function resolveStuckMenus(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $hours    = (int) $request->input('recent_hours', 0);
        $tickets  = $this->stuckMenuTickets($tenantId, $hours);

        $resolved = 0;
        $incomplete = 0;
        $routed = [];

        foreach ($tickets as $ticket) {
            DB::beginTransaction();
            try {
                $res = $this->replayMenu($ticket);
                if ($res['sector'] !== null) {
                    DB::commit();   // persist only tickets that actually routed
                    $resolved++;
                    $routed[] = ['ticket_id' => $ticket->id, 'sector' => $res['sector']];
                } else {
                    DB::rollBack(); // leave unresolved tickets exactly as they were
                    $incomplete++;
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $incomplete++;
            }
        }

        \Log::info('admin.resolve_stuck_menus', [
            'tenant_id' => $tenantId,
            'actor_id'  => $request->user()->id,
            'resolved'  => $resolved,
            'incomplete'=> $incomplete,
        ]);

        return response()->json([
            'total'      => $tickets->count(),
            'resolved'   => $resolved,
            'incomplete' => $incomplete,
            'routed'     => $routed,
        ]);
    }

    /** Tickets parked in the menu with no sector (optionally only recently active). */
    private function stuckMenuTickets(int $tenantId, int $recentHours = 0): Collection
    {
        return Ticket::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'menu')
            ->whereNull('sector_id')
            ->when($recentHours > 0, fn ($q) => $q->where('last_message_at', '>=', now()->subHours($recentHours)))
            ->with('client:id,name,phone')
            ->get();
    }

    /**
     * Replay the digits the client typed (in order) through the fixed MenuEngine,
     * muting all output, so the ticket lands wherever their real choices lead.
     * Returns ['inputs' => string[], 'sector' => ?string].
     */
    private function replayMenu(Ticket $ticket): array
    {
        $digits = Message::query()
            ->where('ticket_id', $ticket->id)
            ->where('direction', 'inbound')
            ->orderByRaw('COALESCE(sent_at, delivered_at, created_at) ASC')
            ->orderBy('id')
            ->pluck('body')
            ->map(fn ($b) => trim((string) $b))
            ->filter(fn ($b) => $b !== '' && strlen($b) <= 2 && ctype_digit($b)) // menu choices, not phone numbers
            ->values();

        // Restart the walk from the root, then feed the real inputs.
        $ticket->forceFill([
            'menu_state' => ['step' => MenuEngine::STATE_ROOT, 'path' => []],
            'status'     => 'menu',
            'sector_id'  => null,
        ])->save();

        $used = [];
        foreach ($digits as $d) {
            $used[] = $d;
            $this->menu->consume($ticket, $d); // mutates + saves; we ignore the reply text
            $ticket->refresh();
            if ($ticket->status !== 'menu') {
                break; // resolved into a sector (or closed via #sair — n/a here)
            }
        }

        return [
            'inputs' => $used,
            'sector' => $ticket->sector_id ? optional($ticket->sector)->name : null,
        ];
    }
    public function previewByPhone(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $digits   = $this->normalize((string) $request->query('phone', ''));

        if ($digits === '') {
            return response()->json(['phone' => null, 'matches' => []]);
        }

        $clients = Client::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($digits) {
                $q->where('phone', $digits)
                  ->orWhere('phone', 'like', "%{$digits}%");
            })
            ->withCount(['tickets as tickets_count'])
            ->get(['id','name','phone','whatsapp_jid','last_message_at']);

        // One grouped query for all matched clients instead of 2 per row.
        $messageCounts = DB::table('messages')
            ->join('tickets', 'messages.ticket_id', '=', 'tickets.id')
            ->whereIn('tickets.client_id', $clients->pluck('id'))
            ->groupBy('tickets.client_id')
            ->selectRaw('tickets.client_id as cid, count(*) as c')
            ->pluck('c', 'cid');

        $matches = $clients->map(fn ($c) => [
            'id'              => $c->id,
            'name'            => $c->name,
            'phone'           => $c->phone,
            'whatsapp_jid'    => $c->whatsapp_jid,
            'last_message_at' => $c->last_message_at,
            'tickets_count'   => $c->tickets_count,
            'messages_count'  => (int) ($messageCounts[$c->id] ?? 0),
        ]);

        return response()->json(['phone' => $digits, 'matches' => $matches]);
    }

    public function wipeByPhone(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'phone'        => ['required','string','min:4','max:32'],
            'keep_contact' => ['nullable','boolean'],
        ]);

        $digits = $this->normalize($data['phone']);
        if ($digits === '') {
            return response()->json(['message' => 'Telefone inválido.'], 422);
        }
        $keepContact = (bool) ($data['keep_contact'] ?? false);

        $clients = Client::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($digits) {
                $q->where('phone', $digits)
                  ->orWhere('phone', 'like', "%{$digits}%");
            })
            ->get();

        if ($clients->isEmpty()) {
            return response()->json(['message' => 'Nenhum contato encontrado para esse número.'], 404);
        }

        $clientIds = $clients->pluck('id')->all();

        $stats = DB::transaction(function () use ($clientIds, $keepContact) {
            $ticketIds = Ticket::whereIn('client_id', $clientIds)->pluck('id')->all();
            $messages  = Message::whereIn('ticket_id', $ticketIds)->count();
            $tickets   = count($ticketIds);

            // FKs are cascade — deleting client wipes tickets/messages/attachments.
            // When keep_contact, only delete tickets (messages cascade).
            if ($keepContact) {
                Ticket::whereIn('id', $ticketIds)->delete();
                $contactsRemoved = 0;
            } else {
                Client::whereIn('id', $clientIds)->delete();
                $contactsRemoved = count($clientIds);
            }

            return [
                'messages_removed' => $messages,
                'tickets_removed'  => $tickets,
                'contacts_removed' => $contactsRemoved,
            ];
        });

        \Log::info('admin.wipe_by_phone', [
            'tenant_id' => $tenantId,
            'actor_id'  => $request->user()->id,
            'phone'     => $digits,
            'stats'     => $stats,
        ]);

        return response()->json(['ok' => true, 'stats' => $stats]);
    }

    public function wipeAllConversations(Request $request): JsonResponse
    {
        $request->validate([
            'confirm'      => ['required','string','in:APAGAR TUDO'],
            'keep_contacts'=> ['nullable','boolean'],
        ]);

        $tenantId      = $request->user()->tenant_id;
        $keepContacts  = (bool) $request->boolean('keep_contacts');

        $stats = DB::transaction(function () use ($tenantId, $keepContacts) {
            $ticketIds = Ticket::where('tenant_id', $tenantId)->pluck('id')->all();
            $messages  = Message::whereIn('ticket_id', $ticketIds)->count();
            $tickets   = count($ticketIds);
            $contactsRemoved = 0;

            if ($keepContacts) {
                Ticket::whereIn('id', $ticketIds)->delete();
            } else {
                $contactsRemoved = Client::where('tenant_id', $tenantId)->count();
                Client::where('tenant_id', $tenantId)->delete();
            }

            return [
                'messages_removed' => $messages,
                'tickets_removed'  => $tickets,
                'contacts_removed' => $contactsRemoved,
            ];
        });

        \Log::warning('admin.wipe_all_conversations', [
            'tenant_id' => $tenantId,
            'actor_id'  => $request->user()->id,
            'stats'     => $stats,
        ]);

        return response()->json(['ok' => true, 'stats' => $stats]);
    }

    private function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
