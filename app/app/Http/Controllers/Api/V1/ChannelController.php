<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Channel\Models\Channel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return response()->json([
            'data' => Channel::where('tenant_id', $tenantId)
                ->orderByDesc('is_primary')
                ->orderBy('type')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'type'       => ['required', 'string', 'in:web_chat,whatsapp,telegram'],
            'name'       => ['required', 'string', 'max:191'],
            'identifier' => ['nullable', 'string', 'max:191'],
            'config'     => ['nullable', 'array'],
            'is_primary' => ['boolean'],
        ]);

        $isPrimary = (bool) ($data['is_primary'] ?? false);
        if ($isPrimary) {
            Channel::where('tenant_id', $tenantId)->update(['is_primary' => false]);
        }

        $channel = Channel::create([
            'tenant_id'  => $tenantId,
            'type'       => $data['type'],
            'name'       => $data['name'],
            'identifier' => $data['identifier'] ?? null,
            'config'     => $data['config'] ?? [],
            'is_active'  => true,
            'is_primary' => $isPrimary,
        ]);

        return response()->json(['data' => $channel], 201);
    }

    public function update(Request $request, Channel $channel): JsonResponse
    {
        abort_unless($channel->tenant_id === $request->user()->tenant_id, 404);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:191'],
            'identifier' => ['nullable', 'string', 'max:191'],
            'config'     => ['nullable', 'array'],
            'is_active'  => ['boolean'],
            'is_primary' => ['boolean'],
        ]);

        if (!empty($data['is_primary'])) {
            Channel::where('tenant_id', $channel->tenant_id)
                ->where('id', '!=', $channel->id)
                ->update(['is_primary' => false]);
        }

        $channel->update($data);

        return response()->json(['data' => $channel]);
    }

    public function destroy(Request $request, Channel $channel): JsonResponse
    {
        abort_unless($channel->tenant_id === $request->user()->tenant_id, 404);
        $channel->delete();

        return response()->json(['ok' => true]);
    }
}
