<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Models\User;
use App\Domain\Ticket\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId  = $request->user()->tenant_id;
        $me        = $request->user();
        $today     = now()->startOfDay();
        $threshold = now()->subMinutes(5);

        $base = User::query()
            ->where('tenant_id', $tenantId)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'superadmin'))
            ->with('roles:id,name')
            ->orderBy('name');

        if ($me->hasRole('attendant') || $me->hasRole('supervisor')) {
            $mySectorIds = $me->sectors()->pluck('sectors.id');
            $base->whereHas('sectors', fn ($q) => $q->whereIn('sectors.id', $mySectorIds));
        }

        $users = $base->get(['id','name','email','phone','is_active','last_seen_at']);

        $inProgress = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open','pending'])
            ->whereNotNull('assigned_to')
            ->select('assigned_to', DB::raw('count(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $resolved = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'closed')
            ->where('closed_at', '>=', $today)
            ->whereNotNull('assigned_to')
            ->select('assigned_to', DB::raw('count(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        return response()->json([
            'data' => $users->map(fn($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'phone'       => $u->phone,
                'is_active'   => (bool) $u->is_active,
                'role'        => optional($u->roles->first())->name ?? 'attendant',
                'in_progress' => (int) ($inProgress[$u->id] ?? 0),
                'resolved'    => (int) ($resolved[$u->id] ?? 0),
                'status'      => ($u->last_seen_at && $u->last_seen_at->gte($threshold)) ? 'online' : 'offline',
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','max:160', Rule::unique('users','email')->whereNull('deleted_at')],
            'phone'    => ['nullable','string','max:32'],
            'password' => ['required','string','min:8'],
            'role'     => ['required', Rule::in(['admin','supervisor','attendant'])],
            'is_active'=> ['boolean'],
        ]);

        $user = User::create([
            'tenant_id' => $tenantId,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);
        $user->syncRoles([$data['role']]);

        return response()->json(['data' => $this->present($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);

        $data = $request->validate([
            'name'     => ['sometimes','required','string','max:120'],
            'email'    => ['sometimes','required','email','max:160', Rule::unique('users','email')->ignore($user->id)->whereNull('deleted_at')],
            'phone'    => ['nullable','string','max:32'],
            'password' => ['nullable','string','min:8'],
            'role'     => ['sometimes','required', Rule::in(['admin','supervisor','attendant'])],
            'is_active'=> ['boolean'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        unset($data['password']);

        $role = $data['role'] ?? null;
        unset($data['role']);

        $user->fill($data)->save();
        if ($role) $user->syncRoles([$role]);

        return response()->json(['data' => $this->present($user->fresh('roles'))]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);
        abort_if($user->id === $request->user()->id, 422, 'Você não pode remover o próprio usuário.');

        $user->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    private function present(User $user): array
    {
        $user->loadMissing('roles:id,name');
        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'is_active' => (bool) $user->is_active,
            'role'      => optional($user->roles->first())->name ?? 'attendant',
            'status'    => 'offline',
        ];
    }
}
