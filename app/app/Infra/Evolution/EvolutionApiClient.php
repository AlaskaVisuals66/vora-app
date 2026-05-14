<?php

namespace App\Infra\Evolution;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client over Evolution API. Endpoints assume Evolution API v2.x.
 * Docs: https://doc.evolution-api.com/
 */
class EvolutionApiClient
{
    public function __construct(
        private readonly string $baseUrl  = '',
        private readonly string $apiKey   = '',
        private readonly int    $timeout  = 15,
    ) {
        $this->baseUrlInit();
    }

    private string $url;
    private string $key;

    private function baseUrlInit(): void
    {
        $this->url = rtrim((string) ($this->baseUrl ?: config('services.evolution.url')), '/');
        $this->key = $this->apiKey ?: (string) config('services.evolution.api_key');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->url)
            ->withHeaders(['apikey' => $this->key, 'Accept' => 'application/json'])
            ->timeout($this->timeout)
            ->retry(2, 250, throw: false);
    }

    public function createInstance(string $instance, array $extra = []): array
    {
        $payload = array_merge([
            'instanceName' => $instance,
            'integration'  => 'WHATSAPP-BAILEYS',
            'qrcode'       => true,
        ], $extra);

        return $this->client()->post('/instance/create', $payload)->throw()->json();
    }

    public function deleteInstance(string $instance): array
    {
        return $this->client()->delete("/instance/delete/{$instance}")->throw()->json();
    }

    public function connect(string $instance): array
    {
        return $this->client()->get("/instance/connect/{$instance}")->throw()->json();
    }

    public function logout(string $instance): array
    {
        return $this->client()->delete("/instance/logout/{$instance}")->throw()->json();
    }

    public function status(string $instance): array
    {
        return $this->client()->get("/instance/connectionState/{$instance}")->throw()->json();
    }

    public function setWebhook(string $instance, string $url, array $events): array
    {
        return $this->client()->post("/webhook/set/{$instance}", [
            'webhook' => [
                'url'     => $url,
                'enabled' => true,
                'events'  => $events,
                'webhookByEvents' => false,
            ],
        ])->throw()->json();
    }

    public function sendText(string $instance, string $jid, string $text, ?string $quotedId = null): array
    {
        $payload = [
            'number' => $jid,
            'text'   => $text,
            'options'=> [ 'delay' => 600, 'presence' => 'composing' ],
        ];
        if ($quotedId) {
            $payload['quoted'] = ['key' => ['id' => $quotedId]];
        }

        $resp = $this->client()->post("/message/sendText/{$instance}", $payload);
        if ($resp->failed()) {
            Log::channel('evolution')->error('sendText failed', ['payload'=>$payload,'body'=>$resp->body()]);
        }
        return $resp->throw()->json();
    }

    public function sendMedia(string $instance, string $jid, string $mediaType, string $mediaUrl, ?string $caption = null, ?string $fileName = null): array
    {
        $payload = [
            'number'    => $jid,
            'mediatype' => $mediaType, // image|video|document|audio
            'media'     => $mediaUrl,
            'caption'   => $caption,
            'fileName'  => $fileName,
        ];
        return $this->client()->post("/message/sendMedia/{$instance}", $payload)->throw()->json();
    }

    public function sendAudio(string $instance, string $jid, string $audioUrl): array
    {
        return $this->client()->post("/message/sendWhatsAppAudio/{$instance}", [
            'number' => $jid,
            'audio'  => $audioUrl,
        ])->throw()->json();
    }

    public function markAsRead(string $instance, string $messageId, string $jid): array
    {
        return $this->client()->post("/chat/markMessageAsRead/{$instance}", [
            'readMessages' => [[ 'id' => $messageId, 'remoteJid' => $jid, 'fromMe' => false ]],
        ])->throw()->json();
    }

    public function presence(string $instance, string $jid, string $presence = 'composing'): array
    {
        return $this->client()->post("/chat/sendPresence/{$instance}", [
            'number'   => $jid, 'presence' => $presence, 'delay' => 1500,
        ])->throw()->json();
    }
}
