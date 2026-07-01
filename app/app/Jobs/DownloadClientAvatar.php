<?php

namespace App\Jobs;

use App\Domain\Client\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Baixa a foto de perfil do WhatsApp (URL do pps.whatsapp.net, que EXPIRA em ~1 dia)
 * enquanto ela ainda está válida e guarda no servidor, trocando o avatar_url por uma
 * URL local permanente. Resolve as fotos que apareciam e depois quebravam (403).
 */
class DownloadClientAvatar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public function backoff(): array { return [5, 20, 60]; }

    public function __construct(public int $clientId, public string $url) {}

    public function handle(): void
    {
        $client = Client::find($this->clientId);
        if (! $client || $this->url === '') {
            return;
        }
        // Já é uma foto local nossa? Não precisa baixar de novo.
        if (str_contains((string) $client->avatar_url, '/storage/media/avatars/')) {
            return;
        }

        try {
            $resp = Http::timeout(25)->get($this->url);
        } catch (\Throwable $e) {
            return;
        }
        if (! $resp->ok() || $resp->body() === '') {
            return; // URL já expirou ou sem imagem — nada a fazer
        }

        $mime = strtolower((string) $resp->header('Content-Type'));
        $ext  = str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'webp') ? 'webp' : 'jpg');
        $path = "avatars/{$client->tenant_id}/{$client->id}.{$ext}";

        Storage::disk('media')->put($path, $resp->body());
        $client->forceFill(['avatar_url' => Storage::disk('media')->url($path)])->save();
    }
}
