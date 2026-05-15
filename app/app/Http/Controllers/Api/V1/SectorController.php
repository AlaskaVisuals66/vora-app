<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sector\Models\Sector;
use App\Domain\Ticket\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SectorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $openCounts = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open','pending','queued'])
            ->whereNotNull('sector_id')
            ->select('sector_id', DB::raw('count(*) as total'))
            ->groupBy('sector_id')
            ->pluck('total', 'sector_id');

        $sectors = Sector::query()
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->whereNull('parent_id')
            ->withCount('attendants')
            ->with(['children' => fn ($q) => $q->where('active', true)->withCount('attendants')->orderBy('order')])
            ->orderBy('order')
            ->get();

        $present = function (Sector $s) use ($openCounts, &$present): array {
            return [
                'id'               => $s->id,
                'parent_id'        => $s->parent_id,
                'name'             => $s->name,
                'slug'             => $s->slug,
                'menu_key'         => $s->menu_key,
                'color'            => $s->color,
                'description'      => $s->description,
                'open_tickets'     => (int) ($openCounts[$s->id] ?? 0),
                'attendants_count' => (int) $s->attendants_count,
                'children_count'   => $s->relationLoaded('children') ? $s->children->count() : 0,
                'children'         => $s->relationLoaded('children')
                    ? $s->children->map($present)->values()
                    : [],
                'ai_settings'      => $s->ai_settings ?? ['ai_enabled' => false, 'ai_prompt' => '', 'n8n_workflow_id' => '', 'n8n_webhook_path' => ''],
            ];
        };

        return response()->json([
            'data' => $sectors->map($present)->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'        => ['required','string','max:120'],
            'parent_id'   => ['nullable','integer', Rule::exists('sectors','id')->where('tenant_id', $tenantId)],
            'menu_key'    => ['nullable','string','max:8'],
            'color'       => ['nullable','string','max:7'],
            'description' => ['nullable','string','max:500'],
        ]);

        $sector = Sector::create([
            'tenant_id'   => $tenantId,
            'parent_id'   => $data['parent_id'] ?? null,
            'name'        => $data['name'],
            'slug'        => $this->uniqueSlug($tenantId, $data['name']),
            'menu_key'    => $data['menu_key'] ?? null,
            'color'       => $data['color'] ?: '#737373',
            'description' => $data['description'] ?? null,
            'active'      => true,
            'order'       => (int) Sector::where('tenant_id', $tenantId)->max('order') + 1,
        ]);

        return response()->json(['data' => $sector], 201);
    }

    public function update(Request $request, Sector $sector): JsonResponse
    {
        abort_unless($sector->tenant_id === $request->user()->tenant_id, 404);

        $data = $request->validate([
            'name'        => ['sometimes','required','string','max:120'],
            'parent_id'   => ['nullable','integer', Rule::exists('sectors','id')->where('tenant_id', $sector->tenant_id)],
            'menu_key'    => ['nullable','string','max:8'],
            'color'       => ['nullable','string','max:7'],
            'description' => ['nullable','string','max:500'],
        ]);

        if (array_key_exists('parent_id', $data) && (int) $data['parent_id'] === $sector->id) {
            return response()->json(['message' => 'Um setor não pode ser pai de si mesmo.'], 422);
        }

        $sector->fill($data)->save();

        return response()->json(['data' => $sector->fresh()]);
    }

    public function destroy(Request $request, Sector $sector): JsonResponse
    {
        abort_unless($sector->tenant_id === $request->user()->tenant_id, 404);

        $sector->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function aiSettings(Request $request, Sector $sector): JsonResponse
    {
        abort_unless($sector->tenant_id === $request->user()->tenant_id, 404);

        $data = $request->validate([
            'ai_enabled'       => ['sometimes', 'boolean'],
            'ai_prompt'        => ['nullable', 'string', 'max:5000'],
            'n8n_workflow_id'  => ['nullable', 'string', 'max:255'],
            'n8n_webhook_path' => ['nullable', 'string', 'max:255'],
        ]);

        $sector->ai_settings = array_merge($sector->ai_settings ?? [], $data);
        $sector->save();

        return response()->json(['data' => $sector->fresh()]);
    }

    public function n8nAction(Request $request, Sector $sector): JsonResponse
    {
        abort_unless($sector->tenant_id === $request->user()->tenant_id, 404);

        $data = $request->validate([
            'type' => ['required', Rule::in(['edit-number', 'edit-conversation'])],
        ]);

        app(\App\Infra\N8n\N8nClient::class)->trigger($data['type'], [
            'sector_id' => $sector->id,
            'tenant_id' => $sector->tenant_id,
        ]);

        return response()->json(['ok' => true]);
    }

    private function uniqueSlug(int $tenantId, string $name): string
    {
        $base = Str::slug($name) ?: 'setor';
        $slug = $base;
        $i = 2;
        while (Sector::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }
}
