<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Client\Models\Client;
use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMaintenanceController extends Controller
{
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
