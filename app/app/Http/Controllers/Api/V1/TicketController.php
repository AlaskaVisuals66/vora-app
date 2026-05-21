<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Models\User;
use App\Domain\Message\Services\OutboundMessageService;
use App\Domain\Sector\Models\Sector;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Services\TicketTransferService;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private readonly OutboundMessageService $outbound,
        private readonly TicketTransferService  $transfers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Ticket::query()
            ->with(['client','assignee:id,name','sector:id,name,color','tags'])
            ->where('tenant_id', $user->tenant_id)
            ->latest('last_message_at');

        if (! $user->isAdmin()) {
            $query->whereIn('sector_id', $user->sectors()->pluck('sectors.id'));
        }

        if ($request->filled('status'))    $query->where('status', $request->get('status'));
        else                                $query->whereNotIn('status', ['closed']);
        if ($request->filled('sector_id')) {
            $sectorId = (int) $request->get('sector_id');
            $childIds = Sector::where('tenant_id', $user->tenant_id)
                ->where('parent_id', $sectorId)
                ->pluck('id')
                ->all();
            $query->whereIn('sector_id', array_merge([$sectorId], $childIds));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->get('search').'%';
            $query->whereHas('client', fn ($q) => $q->where('name','ilike',$term)->orWhere('phone','ilike',$term));
        }

        return TicketResource::collection($query->paginate(30))->response();
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request->user(), $ticket);
        $ticket->load(['client','assignee','sector','tags','transfers.fromUser','transfers.toUser']);
        return response()->json(['data' => new TicketResource($ticket)]);
    }

    public function messages(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request->user(), $ticket);

        // Chronological order: outbound uses sent_at, inbound uses delivered_at.
        // Falling back to id (auto-increment) preserves arrival order when timestamps tie.
        $messages = $ticket->messages()
            ->with(['attachments','sender:id,name'])
            ->orderByRaw('COALESCE(sent_at, delivered_at, created_at) ASC')
            ->orderBy('id')
            ->paginate(50);

        return MessageResource::collection($messages)->response();
    }

    public function send(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request->user(), $ticket);
        $data = $request->validate(['body' => ['required','string','max:8000']]);
        $message = $this->outbound->sendText($ticket, $request->user(), $data['body']);
        return response()->json(['data' => new MessageResource($message)], 201);
    }

    public function transferToSector(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request->user(), $ticket);
        $data = $request->validate([
            'sector_id' => ['required','integer','exists:sectors,id'],
            'reason'    => ['nullable','string','max:500'],
        ]);
        $sector = Sector::query()->where('tenant_id', $request->user()->tenant_id)->findOrFail($data['sector_id']);
        $ticket = $this->transfers->transferToSector($ticket, $sector, $request->user(), $data['reason'] ?? null);
        return response()->json(['data' => new TicketResource($ticket)]);
    }

    public function transferToUser(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request->user(), $ticket);
        $data = $request->validate([
            'user_id' => ['required','integer','exists:users,id'],
            'reason'  => ['nullable','string','max:500'],
        ]);
        $to = User::query()->where('tenant_id', $request->user()->tenant_id)->findOrFail($data['user_id']);
        if (! $to->isAdmin() && ! $to->sectors()->where('sectors.id', $ticket->sector_id)->exists()) {
            return response()->json(['message' => 'O usuário escolhido não pertence ao setor deste ticket.'], 422);
        }
        $ticket = $this->transfers->transferToUser($ticket, $to, $request->user(), $data['reason'] ?? null);
        return response()->json(['data' => new TicketResource($ticket)]);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request->user(), $ticket);
        $ticket->update([
            'status'    => 'closed',
            'closed_at' => now(),
            'resolution_seconds' => $ticket->queued_at
                ? now()->diffInSeconds($ticket->queued_at)
                : null,
        ]);
        return response()->json(['data' => new TicketResource($ticket)]);
    }

    private function authorizeTicket(User $user, Ticket $ticket): void
    {
        if ($ticket->tenant_id !== $user->tenant_id) abort(403);
        if ($user->isAdmin()) return;
        if (! in_array($ticket->sector_id, $user->sectors()->pluck('sectors.id')->all(), true)) {
            abort(403, 'Acesso negado a este ticket.');
        }
    }
}
