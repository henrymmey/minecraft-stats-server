<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\CreateApiKeyRequest;
use App\Models\ApiKey;
use App\Services\ApiKeys\ApiKeyService;
use App\Services\Authentication\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyController
{
    public function __construct(
        private readonly ApiKeyService $keys,
        private readonly WorkspaceContext $context,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');

        $keys = ApiKey::query()
            ->where('workspace_id', $workspaceId)
            ->withCount(['playerRestrictions', 'serverRestrictions', 'seasonRestrictions'])
            ->orderByDesc('created_at')
            ->get();

        $keys->each(fn (ApiKey $key) => $key->setAttribute(
            'scopes',
            DB::table('api_key_scopes')->where('api_key_id', $key->id)->pluck('scope')->values(),
        ));

        return response()->json(['data' => $keys]);
    }

    public function store(CreateApiKeyRequest $request): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');

        $this->assertWorkspaceResources(
            $workspaceId,
            $request->input('player_restrictions', []),
            'players',
        );

        $this->assertWorkspaceResources(
            $workspaceId,
            $request->input('server_restrictions', []),
            'servers',
        );

        $this->assertWorkspaceResources(
            $workspaceId,
            $request->input('season_restrictions', []),
            'seasons',
        );

        [$key, $secret] = $this->keys->create([
            ...$request->validated(),
            'workspace_id' => $workspaceId,
            'created_by' => $request->user('web')->id,
        ]);

        $this->attachRestrictions($key, $request->validated());

        return response()->json([
            'data' => $key,
            'secret' => $secret,
        ], 201);
    }

    public function rotate(Request $request, ApiKey $key): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');
        abort_unless($key->workspace_id === $workspaceId, 404);

        $key->update([
            'enabled' => false,
            'revoked_at' => now(),
        ]);

        [$newKey, $secret] = $this->keys->create([
            'workspace_id' => $workspaceId,
            'name' => $key->name.' (rotated)',
            'type' => $key->type,
            'description' => $key->description,
            'scopes' => DB::table('api_key_scopes')->where('api_key_id', $key->id)->pluck('scope')->all(),
            'created_by' => $request->user('web')->id,
            'expires_at' => $key->expires_at,
        ]);

        DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->pluck('player_id')
            ->each(fn ($id) => DB::table('api_key_player_restrictions')->insert([
                'api_key_id' => $newKey->id,
                'player_id' => $id,
            ]));

        DB::table('api_key_server_restrictions')
            ->where('api_key_id', $key->id)
            ->pluck('server_id')
            ->each(fn ($id) => DB::table('api_key_server_restrictions')->insert([
                'api_key_id' => $newKey->id,
                'server_id' => $id,
            ]));

        DB::table('api_key_season_restrictions')
            ->where('api_key_id', $key->id)
            ->pluck('season_id')
            ->each(fn ($id) => DB::table('api_key_season_restrictions')->insert([
                'api_key_id' => $newKey->id,
                'season_id' => $id,
            ]));

        return response()->json(['data' => $newKey, 'secret' => $secret]);
    }

    public function revoke(Request $request, ApiKey $key): JsonResponse
    {
        abort_unless($key->workspace_id === $request->attributes->get('workspace_id'), 404);

        $key->update([
            'enabled' => false,
            'revoked_at' => now(),
        ]);

        return response()->json(null, 204);
    }

    private function assertWorkspaceResources(string $workspaceId, array $ids, string $table): void
    {
        if ($ids === []) {
            return;
        }

        $count = DB::table($table)
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids)
            ->count();

        abort_unless($count === count(array_unique($ids)), 422);
    }

    private function attachRestrictions(ApiKey $key, array $data): void
    {
        foreach ($data['player_restrictions'] ?? [] as $id) {
            DB::table('api_key_player_restrictions')->insert([
                'api_key_id' => $key->id,
                'player_id' => $id,
            ]);
        }

        foreach ($data['server_restrictions'] ?? [] as $id) {
            DB::table('api_key_server_restrictions')->insert([
                'api_key_id' => $key->id,
                'server_id' => $id,
            ]);
        }

        foreach ($data['season_restrictions'] ?? [] as $id) {
            DB::table('api_key_season_restrictions')->insert([
                'api_key_id' => $key->id,
                'season_id' => $id,
            ]);
        }
    }
}
