<?php

namespace App\Domain\Ticket\Services;

use App\Domain\Sector\Models\Sector;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Str;

/**
 * Stateful menu walker. Persists state inside Ticket::menu_state JSON.
 *
 * Tree (default seed):
 *   1 - Comercial
 *   2 - Financeiro
 *   3 - Manutenção
 *       1 - Assessoria Técnica
 *       2 - Assessoria Científica
 */
class MenuEngine
{
    public const STATE_ROOT      = 'root';
    public const STATE_SUBMENU   = 'submenu';
    public const STATE_RESOLVED  = 'resolved';

    public function start(Ticket $ticket): string
    {
        $ticket->menu_state = ['step' => self::STATE_ROOT, 'path' => []];
        $ticket->save();
        return config('helpdesk.menu.welcome');
    }

    /**
     * Process the user's reply and return:
     *  ['reply' => string, 'sector' => ?Sector, 'done' => bool, 'invalid' => bool]
     */
    public function consume(Ticket $ticket, string $input): array
    {
        $state = $ticket->menu_state ?: ['step' => self::STATE_ROOT, 'path' => []];
        $input = trim($input);

        if (Str::lower($input) === '#sair') {
            $ticket->update(['status' => 'closed', 'closed_at' => now()]);
            return ['reply' => config('helpdesk.menu.closed'), 'sector' => null, 'done' => true, 'invalid' => false];
        }

        $tenantId = $ticket->tenant_id;

        if ($state['step'] === self::STATE_ROOT) {
            $sector = Sector::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('parent_id')
                ->where('active', true)
                ->where('menu_key', $input)
                ->first();

            if (! $sector) {
                return ['reply' => config('helpdesk.menu.welcome'), 'sector' => null, 'done' => false, 'invalid' => true];
            }

            $state['path'][] = $sector->id;

            // Has subsectors? -> ask submenu
            if ($sector->children()->where('active', true)->exists()) {
                $state['step']      = self::STATE_SUBMENU;
                $state['parent_id'] = $sector->id;
                $ticket->menu_state = $state;
                $ticket->save();
                return [
                    'reply'   => $this->renderSubmenu($sector),
                    'sector'  => null,
                    'done'    => false,
                    'invalid' => false,
                ];
            }

            return $this->resolveSector($ticket, $sector, $state);
        }

        if ($state['step'] === self::STATE_SUBMENU) {
            $sector = Sector::query()
                ->where('tenant_id', $tenantId)
                ->where('parent_id', $state['parent_id'])
                ->where('active', true)
                ->where('menu_key', $input)
                ->first();

            if (! $sector) {
                $parent = Sector::query()->find($state['parent_id']);
                return ['reply' => $parent ? $this->renderSubmenu($parent) : config('helpdesk.menu.welcome'), 'sector' => null, 'done' => false, 'invalid' => true];
            }

            $state['path'][] = $sector->id;
            return $this->resolveSector($ticket, $sector, $state);
        }

        return ['reply' => config('helpdesk.menu.welcome'), 'sector' => null, 'done' => false, 'invalid' => true];
    }

    private function resolveSector(Ticket $ticket, Sector $sector, array $state): array
    {
        $state['step'] = self::STATE_RESOLVED;
        $ticket->menu_state = $state;
        $ticket->sector_id  = $sector->id;
        $ticket->status     = 'queued';
        $ticket->queued_at  = now();
        $ticket->save();

        $reply = strtr(config('helpdesk.menu.queued'), [
            '{sector}'   => $sector->name,
            '{protocol}' => $ticket->protocol,
        ]);

        return ['reply' => $reply, 'sector' => $sector, 'done' => true, 'invalid' => false];
    }

    private function renderSubmenu(Sector $parent): string
    {
        $children = $parent->children()->where('active', true)->orderBy('order')->get();
        $lines = [];
        foreach ($children as $c) {
            $lines[] = "{$c->menu_key} - {$c->name}";
        }
        $base = config('helpdesk.menu.maintenance_submenu');
        // If submenu is for "Manutenção" specifically use template; else render dynamic
        if (Str::lower($parent->slug) === 'manutencao' || Str::lower($parent->slug) === 'manutenção') {
            return $base;
        }

        return "Você escolheu {$parent->name}. Selecione:\n\n".implode("\n", $lines);
    }
}
