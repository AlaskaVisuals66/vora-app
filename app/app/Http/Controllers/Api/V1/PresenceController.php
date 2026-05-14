<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\AttendantTyping;
use App\Http\Controllers\Controller;
use App\Infra\Realtime\RealtimePresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function __construct(private readonly RealtimePresence $presence) {}

    public function ping(Request $request): JsonResponse
    {
        $u = $request->user();
        $u->forceFill([ 'last_seen_at' => now(), 'status' => $request->input('status','online') ])->save();
        $this->presence->ping($u->tenant_id, $u->id);
        return response()->json(['ok' => true]);
    }

    public function typing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_id' => ['required','integer'],
            'typing'    => ['required','boolean'],
        ]);
        $u = $request->user();
        broadcast(new AttendantTyping($u->tenant_id, (int) $data['ticket_id'], $u->id, (bool) $data['typing']))->toOthers();
        return response()->json(['ok' => true]);
    }
}
