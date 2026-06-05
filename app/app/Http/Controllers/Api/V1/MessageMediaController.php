<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Http\Controllers\Controller;
use App\Infra\Evolution\EvolutionApiClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MessageMediaController extends Controller
{
    public function __construct(private readonly EvolutionApiClient $evolution) {}

    public function show(Request $request, Message $message): Response
    {
        $tenantId = $request->user()->tenant_id;
        abort_unless($message->tenant_id === $tenantId, 404);
        abort_unless(in_array($message->type, ['image','audio','video','document','sticker','location'], true), 404);

        // Serve from local storage if the message has an attachment (outbound media)
        $attachment = $message->attachments()->first();
        if ($attachment && Storage::disk($attachment->disk)->exists($attachment->path)) {
            $bytes = Storage::disk($attachment->disk)->get($attachment->path);
            return response($bytes, 200)
                ->header('Content-Type', $attachment->mime_type ?: 'application/octet-stream')
                ->header('Content-Disposition', 'inline; filename="'.$this->safeFilename($attachment->original_name).'"')
                ->header('Cache-Control', 'private, max-age=86400');
        }

        // Otherwise fetch from Evolution API (inbound media). Cache key is
        // tenant-scoped so private bytes never collide across tenants in Redis.
        $cacheKey = "msg_media:{$tenantId}:{$message->id}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response($cached['bytes'], 200)
                ->header('Content-Type', $cached['mime'] ?: 'application/octet-stream')
                ->header('Cache-Control', 'private, max-age=86400');
        }

        if (blank($message->external_id)) abort(404, 'Mídia sem identificador.');

        $ticket = $message->ticket;
        $session = $ticket && $ticket->whatsapp_session_id
            ? WhatsappSession::find($ticket->whatsapp_session_id)
            : null;
        if (!$session) abort(404, 'Sessão não encontrada para essa mensagem.');

        // Resolve Evolution per-tenant (gateway settings) instead of global config.
        app()->instance('tenant.id', $tenantId);
        $json = $this->evolution->fetchMediaBase64($session->instance_name, (string) $message->external_id);

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

    /** Strip CR/LF/quotes so a client-supplied filename can't inject headers. */
    private function safeFilename(?string $name): string
    {
        $clean = preg_replace('/[\r\n"\\\\]/', '', (string) ($name ?? ''));
        $clean = trim((string) $clean);
        return $clean !== '' ? $clean : 'file';
    }
}
