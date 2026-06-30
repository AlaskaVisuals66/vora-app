<?php

namespace App\Jobs;

use App\Domain\Message\Models\Attachment;
use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Infra\Evolution\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Baixa a mídia de uma mensagem recebida (foto/áudio/vídeo/documento) da Evolution
 * ASSIM QUE ela chega — enquanto a URL do WhatsApp ainda está válida — e guarda no
 * disco local como Attachment. Depois o MessageMediaController serve do servidor
 * (instantâneo e permanente), em vez de buscar na Evolution na hora (lento / expira).
 */
class DownloadInboundMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public function backoff(): array { return [2, 5, 15, 60, 180]; }

    public function __construct(public int $messageId) {}

    public function handle(EvolutionApiClient $evolution): void
    {
        $message = Message::find($this->messageId);
        if (! $message || blank($message->external_id)) {
            return;
        }
        if (! in_array($message->type, ['image', 'audio', 'video', 'document', 'sticker'], true)) {
            return;
        }
        if ($message->attachments()->exists()) {
            return; // já baixada
        }

        app()->instance('tenant.id', $message->tenant_id);

        // Usa a instância do ticket se estiver conectada; senão, a primária conectada.
        $ticket  = $message->ticket;
        $session = $ticket && $ticket->whatsapp_session_id
            ? WhatsappSession::find($ticket->whatsapp_session_id)
            : null;
        if (! $session || ! in_array($session->state, ['connected', 'open'], true)) {
            $session = WhatsappSession::where('tenant_id', $message->tenant_id)
                ->whereIn('state', ['connected', 'open'])
                ->orderByDesc('is_primary')
                ->first();
        }
        if (! $session) {
            return;
        }

        $json = $evolution->fetchMediaBase64($session->instance_name, (string) $message->external_id);
        $b64  = $json['base64'] ?? null;
        if (! $b64) {
            return; // sem bytes (mídia já expirou no WhatsApp) — não adianta retentar à toa
        }

        $bytes = base64_decode($b64, true);
        if ($bytes === false || $bytes === '') {
            return;
        }

        $mime = $json['mimetype'] ?? ($message->media['mimetype'] ?? 'application/octet-stream');
        $name = $message->media['fileName'] ?? ('media_' . $message->id . '.' . $this->extFromMime($mime));
        $path = "{$message->tenant_id}/{$message->id}/" . Str::random(10) . '.' . $this->extFromMime($mime);

        Storage::disk('media')->put($path, $bytes);

        Attachment::create([
            'tenant_id'     => $message->tenant_id,
            'message_id'    => $message->id,
            'disk'          => 'media',
            'path'          => $path,
            'mime_type'     => $mime,
            'original_name' => $name,
            'size_bytes'    => strlen($bytes),
        ]);

        Log::channel('evolution')->info('inbound_media.stored', [
            'message_id' => $message->id,
            'type'       => $message->type,
            'size'       => strlen($bytes),
        ]);
    }

    private function extFromMime(string $mime): string
    {
        $mime = strtolower(explode(';', $mime)[0]);

        return [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'image/gif' => 'gif',
            'audio/ogg' => 'ogg', 'audio/opus' => 'ogg', 'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a', 'audio/amr' => 'amr', 'audio/aac' => 'aac', 'audio/wav' => 'wav',
            'video/mp4' => 'mp4', 'video/3gpp' => '3gp', 'video/quicktime' => 'mov',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ][$mime] ?? 'bin';
    }
}
