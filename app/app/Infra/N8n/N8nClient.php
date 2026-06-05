<?php

namespace App\Infra\N8n;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Wrapper for n8n public API and webhook triggers.
 * The token must be provided via env (N8N_API_TOKEN). Never commit it.
 */
class N8nClient
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.n8n.base_url'), '/'))
            ->withToken((string) config('services.n8n.api_token'))
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(10)
            ->retry(2, 200, throw: false);
    }

    /** Trigger an arbitrary n8n webhook (workflow that listens on /webhook/<path>). */
    public function trigger(string $path, array $payload): array
    {
        $path = ltrim(trim($path), '/');

        // The path comes from tenant-editable sector settings — keep it confined
        // to the n8n /webhook/ namespace. Reject traversal, schemes and stray chars.
        if ($path === '' || str_contains($path, '..') || ! preg_match('#^[A-Za-z0-9/_-]+$#', $path)) {
            \Log::warning('n8n.trigger.invalid_path', ['path' => $path]);
            return ['ok' => false, 'error' => 'invalid_webhook_path'];
        }

        $url = rtrim((string) config('services.n8n.base_url'), '/').'/webhook/'.$path;
        $resp = Http::timeout(10)
            ->withOptions(['allow_redirects' => false])
            ->retry(2, 200, throw: false)
            ->post($url, $payload);
        return $resp->json() ?? ['ok' => $resp->successful(), 'status' => $resp->status()];
    }

    /** List workflows via the public API. */
    public function workflows(int $limit = 50): array
    {
        return $this->client()->get('/api/v1/workflows', ['limit' => $limit])->json() ?? [];
    }

    public function activate(string $id): array
    {
        return $this->client()->post("/api/v1/workflows/{$id}/activate")->json() ?? [];
    }

    public function deactivate(string $id): array
    {
        return $this->client()->post("/api/v1/workflows/{$id}/deactivate")->json() ?? [];
    }
}
