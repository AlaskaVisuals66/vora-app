<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MessageMediaController extends Controller
{
    public function show(Request $request, Message $message): Response
    {
        $tenantId = $request->user()->tenant_id;
        abort_unless($message->tenant_id === $tenantId, 404);
        abort_unless(in_array($message->type, ['image','audio','video','document','sticker'], true), 404);

        $cacheKey = "msg_media:{$message->id}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response($cached['bytes'], 200)
                ->header('Content-Type', $cached['mime'] ?: 'application/octet-stream')
                ->header('Cache-Control', 'private, max-age=86400');
        }

        $ticket = $message->ticket;
        $session = $ticket && $ticket->whatsapp_session_id
            ? WhatsappSession::find($ticket->whatsapp_session_id)
            : null;
        if (!$session) abort(404, 'Sessão não encontrada para essa mensagem.');

        $base = rtrim((string) config('services.evolution.url'), '/');
        $key  = (string) config('services.evolution.api_key');
        if (!$base || !$key) abort(503, 'Evolution não configurado.');

        $body = [
            'message' => [
                'key' => [
                    'id' => (string) $message->external_id,
                ],
            ],
            'convertToMp4' => false,
        ];

        $resp = Http::baseUrl($base)
            ->withHeaders(['apikey' => $key, 'Accept' => 'application/json'])
            ->timeout(30)
            ->post('/chat/getBase64FromMediaMessage/' . rawurlencode($session->instance_name), $body);

        if (!$resp->successful()) {
            abort(502, 'Falha ao buscar mídia: ' . $resp->status());
        }

        $json = $resp->json();
        $b64  = $json['base64'] ?? null;
        $mime = $json['mimetype'] ?? ($message->media['mimetype'] ?? null);
        if (!$b64) abort(502, 'Mídia indisponível.');

        $bytes = base64_decode($b64, true);
        if ($bytes === false) abort(502, 'Mídia corrompida.');

        Cache::put($cacheKey, ['bytes' => $bytes, 'mime' => $mime], now()->addHours(24));

        return response($bytes, 200)
            ->header('Content-Type', $mime ?: 'application/octet-stream')
            ->header('Cache-Control', 'private, max-age=86400');
    }
}
