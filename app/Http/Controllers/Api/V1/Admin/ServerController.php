<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\MinecraftServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServerController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MinecraftServer::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->orderBy('display_name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'display_name' => ['required', 'string', 'min:1', 'max:160'],
            'hostname' => ['required', 'string', 'min:1', 'max:253'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $hostname = Str::lower(rtrim($data['hostname'], '.'));

        $server = MinecraftServer::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $request->attributes->get('workspace_id'),
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'hostname' => $hostname,
            'port' => $data['port'],
            'enabled' => $data['enabled'] ?? true,
        ]);

        return response()->json(['data' => $server], 201);
    }

    public function update(Request $request, MinecraftServer $server): JsonResponse
    {
        abort_unless($server->workspace_id === $request->attributes->get('workspace_id'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'display_name' => ['required', 'string', 'min:1', 'max:160'],
            'hostname' => ['required', 'string', 'min:1', 'max:253'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'enabled' => ['required', 'boolean'],
        ]);

        $server->update([
            ...$data,
            'hostname' => Str::lower(rtrim($data['hostname'], '.')),
        ]);

        return response()->json(['data' => $server->fresh()]);
    }
}
