<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Client\Models\Client;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Domain\Ticket\Services\ProtocolGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function __construct(private readonly ProtocolGenerator $protocols) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $query = Client::query()
            ->where('tenant_id', $tenantId)
            ->withCount(['tickets'])
            ->orderByDesc('last_message_at')
            ->orderBy('name');

        if ($search = trim((string) $request->get('search', ''))) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like, $search) {
                $q->where('name', 'like', $like)
                  ->orWhere('phone', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }

        $perPage = min(100, max(10, (int) $request->get('per_page', 50)));

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validate([
            'name'  => ['nullable','string','max:191'],
            'phone' => ['required','string','min:4','max:32'],
            'email' => ['nullable','email','max:191'],
        ]);
        $phone = preg_replace('/\D+/', '', $data['phone']);
        if (strlen($phone) < 8) {
            return response()->json(['message' => 'Telefone inválido.'], 422);
        }
        $client = Client::firstOrCreate(
            ['tenant_id' => $tenantId, 'phone' => $phone],
            [
                'name'         => $data['name'] ?: $phone,
                'whatsapp_jid' => $phone . '@s.whatsapp.net',
                'email'        => $data['email'] ?? null,
            ]
        );
        // Update name/email if provided and changed
        $dirty = [];
        if (!empty($data['name']) && $client->name !== $data['name']) $dirty['name'] = $data['name'];
        if (!empty($data['email']) && $client->email !== $data['email']) $dirty['email'] = $data['email'];
        if ($dirty) $client->update($dirty);

        return response()->json(['client' => $client->refresh()], 201);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'phone'     => ['nullable','string','max:32'],
            'client_id' => ['nullable','integer'],
            'name'      => ['nullable','string','max:191'],
        ]);

        if (empty($data['phone']) && empty($data['client_id'])) {
            return response()->json(['message' => 'Informe telefone ou contato.'], 422);
        }

        $client = null;
        if (!empty($data['client_id'])) {
            $client = Client::where('tenant_id', $tenantId)->find($data['client_id']);
            if (!$client) return response()->json(['message' => 'Contato não encontrado.'], 404);
        } else {
            $phone = preg_replace('/\D+/', '', $data['phone']);
            if (strlen($phone) < 8) {
                return response()->json(['message' => 'Telefone inválido.'], 422);
            }
            $client = Client::firstOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $phone],
                [
                    'name'         => $data['name'] ?: $phone,
                    'whatsapp_jid' => $phone . '@s.whatsapp.net',
                ]
            );
        }

        // Prefer any existing ticket (latest first). Reopen if it was closed/resolved.
        $existing = $client->tickets()->latest('id')->first();
        if ($existing) {
            if (in_array($existing->status, ['closed','resolved'], true)) {
                $existing->forceFill(['status' => 'open', 'closed_at' => null])->save();
            }
            return response()->json(['ticket_id' => $existing->id, 'reused' => true]);
        }

        $session = WhatsappSession::where('tenant_id', $tenantId)
            ->where('is_primary', true)
            ->first();
        if (!$session) {
            $session = WhatsappSession::where('tenant_id', $tenantId)->first();
        }

        $ticket = DB::transaction(function () use ($tenantId, $client, $session) {
            return Ticket::create([
                'tenant_id'           => $tenantId,
                'protocol'            => $this->protocols->next($tenantId),
                'client_id'           => $client->id,
                'whatsapp_session_id' => $session?->id,
                'status'              => 'open',
                'channel'             => 'whatsapp',
            ]);
        });

        return response()->json(['ticket_id' => $ticket->id, 'reused' => false], 201);
    }
}
