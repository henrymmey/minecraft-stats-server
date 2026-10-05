<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeasonController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Season::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->orderByDesc('active')
                ->orderByDesc('started_at')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'slug' => ['required', 'string', 'min:1', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $workspaceId = $request->attributes->get('workspace_id');

        $season = DB::transaction(function () use ($data, $workspaceId): Season {
            if (($data['active'] ?? false) === true) {
                Season::query()
                    ->where('workspace_id', $workspaceId)
                    ->update(['active' => false]);
            }

            return Season::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'started_at' => $data['started_at'] ?? null,
                'ended_at' => $data['ended_at'] ?? null,
                'active' => $data['active'] ?? false,
            ]);
        });

        return response()->json(['data' => $season], 201);
    }

    public function activate(Request $request, Season $season): JsonResponse
    {
        abort_unless($season->workspace_id === $request->attributes->get('workspace_id'), 404);

        DB::transaction(function () use ($season): void {
            Season::query()
                ->where('workspace_id', $season->workspace_id)
                ->update(['active' => false]);

            $season->update(['active' => true]);
        });

        return response()->json(['data' => $season->fresh()]);
    }
}
