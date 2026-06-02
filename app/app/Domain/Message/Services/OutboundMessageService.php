<?php

namespace App\Domain\Message\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Message\Models\Attachment;
use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\Ticket;
use App\Events\MessageSent;
use App\Infra\Evolution\EvolutionApiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OutboundMessageService
{
    public function __construct(private readonly EvolutionApiClient $evolution) {}

    public function sendText(Ticket $ticket, User $sender, string $text): Message
    {
        $message = Message::create([
            'tenant_id'      => $ticket->tenant_id,
            'ticket_id'      => $ticket->id,
            'sender_user_id' => $sender->id,
            'direction'      => 'outbound',
            'type'           => 'text',
            'body'           => $text,
            'status'         => 'queued',
        ]);

        $session = $ticket->whatsapp_session_id
            ? \App\Domain\Ticket\Models\WhatsappSession::find($ticket->whatsapp_session_id)
            : null;

        $client = $ticket->client;
        if ($session && $client?->whatsapp_jid) {
            $resp = $this->evolution->sendText($session->instance_name, $client->whatsapp_jid, $text);
            $message->update([
                'external_id' => $resp['key']['id'] ?? null,
                'status'      => 'sent',
                'sent_at'     => now(),
            ]);
        } else {
            $message->update(['status' => 'failed', 'failure_reason' => 'no_session_or_jid']);
        }

        $this->finalizeTicket($ticket);
        broadcast(new MessageSent($message))->toOthers();
        return $message;
    }

    public function sendMedia(Ticket $ticket, User $sender, UploadedFile $file, ?string $caption = null): Message
    {
        $mime = $file->getMimeType();
        $type = $this->guessType($mime);

        $message = Message::create([
            'tenant_id'      => $ticket->tenant_id,
            'ticket_id'      => $ticket->id,
            'sender_user_id' => $sender->id,
            'direction'      => 'outbound',
            'type'           => $type,
            'body'           => $caption,
            'media'          => ['mimetype' => $mime, 'fileName' => $file->getClientOriginalName()],
            'status'         => 'queued',
        ]);

        $dir = "media/{$ticket->tenant_id}/{$message->id}";
        $disk = 'local';
        $path = $file->store($dir, $disk);

        Attachment::create([
            'tenant_id'     => $ticket->tenant_id,
            'message_id'    => $message->id,
            'disk'          => $disk,
            'path'          => $path,
            'mime_type'     => $mime,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes'    => $file->getSize(),
        ]);

        $session = $ticket->whatsapp_session_id
            ? \App\Domain\Ticket\Models\WhatsappSession::find($ticket->whatsapp_session_id)
            : null;

        $client = $ticket->client;
        if ($session && $client?->whatsapp_jid) {
            $fullPath = Storage::disk($disk)->path($path);
            $base64 = base64_encode(file_get_contents($fullPath));
            $dataUri = "data:{$mime};base64,{$base64}";

            try {
                if ($type === 'audio') {
                    $resp = $this->evolution->sendAudio($session->instance_name, $client->whatsapp_jid, $dataUri);
                } else {
                    $mediaType = match ($type) {
                        'image'    => 'image',
                        'video'    => 'video',
                        default    => 'document',
                    };
                    $resp = $this->evolution->sendMedia(
                        $session->instance_name,
                        $client->whatsapp_jid,
                        $mediaType,
                        $dataUri,
                        $caption,
                        $file->getClientOriginalName(),
                    );
                }
                $message->update([
                    'external_id' => $resp['key']['id'] ?? null,
                    'status'      => 'sent',
                    'sent_at'     => now(),
                ]);
            } catch (\Throwable $e) {
                \Log::channel('evolution')->error('sendMedia failed', [
                    'message_id' => $message->id,
                    'error'      => $e->getMessage(),
                ]);
                $message->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            }
        } else {
            $message->update(['status' => 'failed', 'failure_reason' => 'no_session_or_jid']);
        }

        $this->finalizeTicket($ticket);
        broadcast(new MessageSent($message))->toOthers();
        return $message;
    }

    private function guessType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/')   => 'image',
            str_starts_with($mime, 'video/')   => 'video',
            str_starts_with($mime, 'audio/')   => 'audio',
            default                             => 'document',
        };
    }

    private function finalizeTicket(Ticket $ticket): void
    {
        $ticket->increment('messages_count');
        $ticket->update(['last_message_at' => now()]);

        if ($ticket->first_response_at === null) {
            $first = (int) now()->diffInSeconds($ticket->queued_at ?? $ticket->created_at);
            $ticket->update(['first_response_at' => now(), 'first_response_seconds' => $first]);
        }
    }
}
