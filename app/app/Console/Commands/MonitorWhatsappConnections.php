<?php

namespace App\Console\Commands;

use App\Domain\Ticket\Models\WhatsappSession;
use App\Infra\Evolution\EvolutionApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Checa o estado de conexão de cada número WhatsApp (instância Evolution) e,
 * quando um número que estava conectado CAI, manda um alerta por WhatsApp para
 * o responsável — por outro número que ainda esteja conectado (não dá pra
 * avisar pelo número que caiu). Roda no scheduler de 2 em 2 minutos.
 */
class MonitorWhatsappConnections extends Command
{
    protected $signature = 'app:monitor-whatsapp';
    protected $description = 'Monitora conexões WhatsApp e alerta por WhatsApp quando um número cai.';

    /** Número (E.164 sem +) que recebe os alertas de queda. */
    private const ALERT_TO = '5521982212296';

    public function handle(EvolutionApiClient $evo): int
    {
        foreach (WhatsappSession::all() as $session) {
            app()->instance('tenant.id', $session->tenant_id);

            try {
                $resp = $evo->status($session->instance_name);
            } catch (\Throwable $e) {
                continue; // não conseguiu checar agora — não muda nada
            }

            $raw = strtolower((string) (
                $resp['instance']['state']
                ?? $resp['instance']['connectionStatus']
                ?? $resp['state']
                ?? ''
            ));
            if ($raw === '') {
                continue;
            }

            $connected = in_array($raw, ['open', 'connected'], true);
            $next = $connected ? 'connected' : ($raw === 'connecting' ? 'connecting' : 'disconnected');
            $was = $session->state;

            // Transição: estava conectado e caiu -> alerta UMA vez (evita spam).
            if (in_array($was, ['connected', 'open'], true) && ! $connected) {
                $this->notifyDown($evo, $session);
            }

            if ($next !== $was) {
                $session->forceFill(['state' => $next])->save();
            }
        }

        return self::SUCCESS;
    }

    private function notifyDown(EvolutionApiClient $evo, WhatsappSession $down): void
    {
        // Envia por OUTRO número conectado (o que caiu não consegue mandar nada).
        $sender = WhatsappSession::where('tenant_id', $down->tenant_id)
            ->whereIn('state', ['connected', 'open'])
            ->where('id', '!=', $down->id)
            ->orderByDesc('is_primary')
            ->first();

        $label = $down->display_name ?: $down->instance_name;
        $text = "⚠️ ALERTA: o WhatsApp \"{$label}\" DESCONECTOU do sistema agora. "
            . "As mensagens desse número param de entrar até reconectar. "
            . "Entre no painel e reconecte o QR Code.";

        if (! $sender) {
            Log::channel('evolution')->error('whatsapp.down.no_sender', ['down' => $down->instance_name]);
            return;
        }

        try {
            $evo->sendText($sender->instance_name, self::ALERT_TO . '@s.whatsapp.net', $text);
            Log::channel('evolution')->warning('whatsapp.down.alert_sent', [
                'down' => $down->instance_name,
                'via'  => $sender->instance_name,
            ]);
        } catch (\Throwable $e) {
            Log::channel('evolution')->error('whatsapp.down.alert_failed', ['error' => $e->getMessage()]);
        }
    }
}
