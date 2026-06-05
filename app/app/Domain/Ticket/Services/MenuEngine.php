<?php

namespace App\Domain\Ticket\Services;

use App\Domain\Sector\Models\Sector;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Stateful menu walker. Persists state inside Ticket::menu_state JSON.
 *
 * The menu is rendered DYNAMICALLY from the tenant's active sectors and the
 * user's reply is matched by POSITION (the number shown). This guarantees the
 * options displayed always correspond to a routable sector — no drift between
 * a hardcoded banner and the DB rows used for routing.
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
        return $this->renderRootMenu($ticket->tenant_id);
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
            $roots  = $this->rootSectors($tenantId);
            $sector = $this->pick($roots, $input);

            if (! $sector) {
                return $this->invalid();
            }

            $state['path'][] = $sector->id;

            // Has subsectors? -> ask submenu
            $children = $this->childSectors($sector->id);
            if ($children->isNotEmpty()) {
                $state['step']      = self::STATE_SUBMENU;
                $state['parent_id'] = $sector->id;
                $ticket->menu_state = $state;
                $ticket->save();
                return [
                    'reply'   => $this->renderSubmenu($sector, $children),
                    'sector'  => null,
                    'done'    => false,
                    'invalid' => false,
                ];
            }

            return $this->resolveSector($ticket, $sector, $state);
        }

        if ($state['step'] === self::STATE_SUBMENU) {
            $children = $this->childSectors($state['parent_id'] ?? 0);
            $sector   = $this->pick($children, $input);

            if (! $sector) {
                return $this->invalid();
            }

            $state['path'][] = $sector->id;
            return $this->resolveSector($ticket, $sector, $state);
        }

        return $this->invalid();
    }

    /** Active root sectors for the tenant, in display order. */
    private function rootSectors(int $tenantId): Collection
    {
        return Sector::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('parent_id')
            ->where('active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->values();
    }

    /** Active children of a sector, in display order. */
    private function childSectors(int $parentId): Collection
    {
        return Sector::query()
            ->where('parent_id', $parentId)
            ->where('active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->values();
    }

    /** Map the typed number to the Nth sector (1-based). Null if out of range / not a number. */
    private function pick(Collection $sectors, string $input): ?Sector
    {
        if ($input === '' || ! ctype_digit($input)) {
            return null;
        }
        return $sectors->get((int) $input - 1);
    }

    private function invalid(): array
    {
        return ['reply' => config('helpdesk.menu.invalid'), 'sector' => null, 'done' => false, 'invalid' => true];
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

    private function renderRootMenu(int $tenantId): string
    {
        $sectors = $this->rootSectors($tenantId);

        $options = $sectors->isEmpty()
            ? 'Nenhum setor disponível no momento.'
            : $sectors->map(fn (Sector $s, int $i) => ($i + 1).' - '.$s->name)->implode("\n");

        $tpl = (string) config('helpdesk.menu.welcome');

        return str_contains($tpl, '{options}')
            ? str_replace('{options}', $options, $tpl)
            : $tpl."\n\n".$options;
    }

    private function renderSubmenu(Sector $parent, Collection $children): string
    {
        $lines = $children->map(fn (Sector $c, int $i) => ($i + 1).' - '.$c->name)->implode("\n");

        return "Você escolheu {$parent->name}. Selecione:\n\n".$lines;
    }
}
