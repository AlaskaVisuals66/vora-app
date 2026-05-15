<?php

namespace App\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Ticket\Models\WhatsappSession;

class WhatsappSessionPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function create(User $user): bool  { return true; }
    public function delete(User $user, WhatsappSession $session): bool { return $user->isAdmin(); }
    public function reconnect(User $user, WhatsappSession $session): bool { return true; }
}
