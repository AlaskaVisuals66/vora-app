<?php

namespace App\Jobs;

use App\Domain\Client\Models\Client;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Infra\Evolution\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the full contact list of one WhatsApp number (instance) from Evolution
 * and upserts each person into `clients`, linking them to this number and
 * filling in the real WhatsApp name (pushName). Runs on the queue because a
 * number can have thousands of contacts.
 */
class ImportInstanceContacts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $sessionId) {}

    public function handle(EvolutionApiClient $evolution): void
    {
        $session = WhatsappSession::find($this->sessionId);
        if (! $session) {
            return;
        }

        // Tenant context so EvolutionApiClient resolves this tenant's gateway config.
        app()->instance('tenant.id', $session->tenant_id);

        try {
            $contacts = $evolution->findContacts($session->instance_name);
        } catch (\Throwable $e) {
            Log::channel('evolution')->error('import_contacts.failed', [
                'instance' => $session->instance_name,
                'error'    => $e->getMessage(),
            ]);
            return;
        }

        $imported = 0;
        foreach ($contacts as $c) {
            if (! is_array($c)) {
                continue;
            }
            // The WhatsApp jid is in `remoteJid` (`id` is Evolution's internal id).
            $jid = $c['remoteJid'] ?? null;
            if (! is_string($jid) && isset($c['id']) && str_contains((string) $c['id'], '@')) {
                $jid = (string) $c['id'];
            }
            if (! is_string($jid)) {
                continue;
            }
            // Skip groups (@g.us) and broadcast lists — keep individual people,
            // INCLUDING @lid (privacy) contacts. @lid carries no real phone, so we
            // key them by the lid number; the WhatsApp name (pushName) still comes
            // through so the contact is recognizable.
            if (str_ends_with($jid, '@g.us') || str_contains($jid, 'broadcast')) {
                continue;
            }
            $phone = \App\Support\Phone::canonical(explode('@', $jid)[0]);
            if (strlen($phone) < 8) {
                continue;
            }

            $waName = trim((string) ($c['pushName'] ?? $c['name'] ?? $c['verifiedName'] ?? ''));
            $picUrl = (is_string($c['profilePicUrl'] ?? null) && $c['profilePicUrl'] !== '') ? $c['profilePicUrl'] : null;

            $client = Client::firstOrCreate(
                ['tenant_id' => $session->tenant_id, 'phone' => $phone],
                [
                    'name'         => $waName !== '' ? $waName : $phone,
                    'whatsapp_jid' => $jid,
                    'avatar_url'   => $picUrl,
                ]
            );

            // Backfill the real WhatsApp name when we previously only had the phone.
            if ($waName !== '' && ($client->name === null || $client->name === $client->phone)) {
                $client->update(['name' => $waName]);
            }

            // Backfill / refresh the WhatsApp profile photo whenever Evolution gives us one.
            if ($picUrl !== null && $client->avatar_url !== $picUrl) {
                $client->update(['avatar_url' => $picUrl]);
            }

            $client->sessions()->syncWithoutDetaching([
                $session->id => ['name_on_instance' => $waName !== '' ? $waName : null],
            ]);

            $imported++;
        }

        Log::channel('evolution')->info('import_contacts.done', [
            'instance' => $session->instance_name,
            'imported' => $imported,
        ]);
    }
}
