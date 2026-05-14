<?php

namespace App\Domain\Ticket\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Sector\Models\Sector;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketTransfer;
use App\Events\TicketAssigned;
use App\Events\TicketTransferred;
use Illuminate\Support\Facades\DB;

class TicketTransferService
{
    public function __construct(private readonly AttendantDistributor $distributor) {}

    public function transferToSector(Ticket $ticket, Sector $sector, ?User $by = null, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $sector, $by, $reason) {
            TicketTransfer::create([
                'ticket_id'      => $ticket->id,
                'from_user_id'   => $ticket->assigned_to,
                'to_user_id'     => null,
                'from_sector_id' => $ticket->sector_id,
                'to_sector_id'   => $sector->id,
                'reason'         => $reason,
                'transferred_at' => now(),
            ]);

            $ticket->update([
                'sector_id'   => $sector->id,
                'assigned_to' => null,
                'status'      => 'queued',
                'queued_at'   => now(),
            ]);

            broadcast(new TicketTransferred($ticket));
            $this->distributor->assign($ticket);
            broadcast(new TicketAssigned($ticket->refresh()));
            return $ticket->refresh();
        });
    }

    public function transferToUser(Ticket $ticket, User $to, ?User $by = null, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $reason) {
            TicketTransfer::create([
                'ticket_id'      => $ticket->id,
                'from_user_id'   => $ticket->assigned_to,
                'to_user_id'     => $to->id,
                'from_sector_id' => $ticket->sector_id,
                'to_sector_id'   => $ticket->sector_id,
                'reason'         => $reason,
                'transferred_at' => now(),
            ]);

            $ticket->update([
                'assigned_to' => $to->id,
                'assigned_at' => now(),
                'status'      => 'open',
            ]);
            broadcast(new TicketAssigned($ticket->refresh()));
            return $ticket->refresh();
        });
    }
}
