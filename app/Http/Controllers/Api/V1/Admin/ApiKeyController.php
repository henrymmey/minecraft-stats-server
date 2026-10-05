<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\CreateApiKeyRequest;
use App\Http\Requests\UpdateApiKeyRequest;
use App\Models\ApiKey;
use App\Services\ApiKeys\ApiKeyService;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyController
{
    public function __construct(
        private readonly ApiKeyService $keys,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');

        $keys = ApiKey::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get();

        $keys->each(function (ApiKey $key): void {
            $key->setAttribute(
                'scopes',
                DB::table('api_key_scopes')
                    ->where('api_key_id', $key->id)
                    ->pluck('scope')
                    ->values(),
            );
            $key->setAttribute(
                'player_restrictions',
                DB::table('api_key_player_restrictions')
                    ->where('api_key_id', $key->id)
                    ->pluck('player_id')
                    ->values(),
            );
            $key->setAttribute(
                'server_restrictions',
                DB::table('api_key_server_restrictions')
                    ->where('api_key_id', $key->id)
                    ->pluck('server_id')
                    ->values(),
            );
            $key->setAttribute(
                'season_restrictions',
                DB::table('api_key_season_restrictions')
                    ->where('api_key_id', $key->id)
                    ->pluck('season_id')
                    ->values(),
            );
        });

        return response()->json(['data' => $keys]);
    }

    public function store(CreateApiKeyRequest $request): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');
        $data = $request->validated();

        $this->assertWorkspaceResources($workspaceId, $data['player_restrictions'] ?? [], 'players');
        $this->assertWorkspaceResources($workspaceId, $data['server_restrictions'] ?? [], 'servers');
        $this->assertWorkspaceResources($workspaceId, $data['season_restrictions'] ?? [], 'seasons');

        [$key, $secret] = DB::transaction(function () use ($data, $workspaceId, $request): array {
            [$key, $secret] = $this->keys->create([
                ...$data,
                'workspace_id' => $workspaceId,
                'created_by' => $request->user('web')->id,
            ]);

            $this->replaceRestrictions($key, $data);

            $this->audit->record(
                $workspaceId,
                $request->user('web')->id,
                'API_KEY_CREATED',
                'api_key',
                $key->id,
                ['type' => $key->type, 'scopes' => $data['scopes']],
            );

            return [$key, $secret];
        });

        return response()->json([
            'data' => $key,
            'secret' => $secret,
        ], 201);
    }

    public function update(UpdateApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        abort_unless($key->workspace_id === $request->attributes->get('workspace_id'), 404);

        $data = $request->validated();
        $workspaceId = $request->attributes->get('workspace_id');

        $this->assertWorkspaceResources($workspaceId, $data['player_restrictions'] ?? [], 'players');
        $this->assertWorkspaceResources($workspaceId, $data['server_restrictions'] ?? [], 'servers');
        $this->assertWorkspaceResources($workspaceId, $data['season_restrictions'] ?? [], 'seasons');

        if ($key->type === 'client' && !in_array('ingest:write', $data['scopes'], true)) {
            abort(422, 'Client keys require the ingest:write scope.');
        }

        DB::transaction(function () use ($key, $data): void {
            $key->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ]);

            DB::table('api_key_scopes')->where('api_key_id', $key->id)->delete();
            DB::table('api_key_scopes')->insert(
                array_map(fn (string $scope) => [
                    'api_key_id' => $key->id,
                    'scope' => $scope,
                ], $data['scopes']),
            );

            $this->replaceRestrictionTable('api_key_player_restrictions', 'player_id', $key->id, $data['player_restrictions'] ?? []);
            $this->replaceRestrictionTable('api_key_server_restrictions', 'server_id', $key->id, $data['server_restrictions'] ?? []);
            $this->replaceRestrictionTable('api_key_season_restrictions', 'season_id', $key->id, $data['season_restrictions'] ?? []);
        });

        $this->audit->record(
            $workspaceId,
            $request->user('web')->id,
            'API_KEY_UPDATED',
            'api_key',
            $key->id,
            ['scopes' => $data['scopes']],
        );

        return response()->json(['data' => $this->serializedKey($key->fresh())]);
    }

    public function rotate(Request $request, ApiKey $key): JsonResponse
    {
        abort_unless($key->workspace_id === $request->attributes->get('workspace_id'), 404);

        $result = DB::transaction(function () use ($request, $key): array {
            $scopes = DB::table('api_key_scopes')->where('api_key_id', $key->id)->pluck('scope')->all();
            $players = DB::table('api_key_player_restrictions')->where('api_key_id', $key->id)->pluck('player_id')->all();
            $servers = DB::table('api_key_server_restrictions')->where('api_key_id', $key->id)->pluck('server_id')->all();
            $seasons = DB::table('api_key_season_restrictions')->where('api_key_id', $key->id)->pluck('season_id')->all();

            $key->update([
                'enabled' => false,
                'revoked_at' => now(),
            ]);

            [$newKey, $secret] = $this->keys->create([
                'workspace_id' => $key->workspace_id,
                'name' => $key->name.' (rotated)',
                'type' => $key->type,
                'description' => $key->description,
                'scopes' => $scopes,
                'created_by' => $request->user('web')->id,
                'expires_at' => $key->expires_at,
            ]);

            $this->replaceRestrictionTable('api_key_player_restrictions', 'player_id', $newKey->id, $players);
            $this->replaceRestrictionTable('api_key_server_restrictions', 'server_id', $newKey->id, $servers);
            $this->replaceRestrictionTable('api_key_season_restrictions', 'season_id', $newKey->id, $seasons);

            return [$newKey, $secret];
        });

        $this->audit->record(
            $key->workspace_id,
            $request->user('web')->id,
            'API_KEY_ROTATED',
            'api_key',
            $key->id,
            ['replacement_key_id' => $result[0]->id],
        );

        return response()->json([
            'data' => $this->serializedKey($result[0]),
            'secret' => $result[1],
        ]);
    }

    public function revoke(Request $request, ApiKey $key): JsonResponse
    {
        abort_unless($key->workspace_id === $request->attributes->get('workspace_id'), 404);

        $key->update([
            'enabled' => false,
            'revoked_at' => now(),
        ]);

        $this->audit->record(
            $key->workspace_id,
            $request->user('web')->id,
            'API_KEY_REVOKED',
            'api_key',
            $key->id,
        );

        return response()->json(null, 204);
    }

    private function assertWorkspaceResources(string $workspaceId, array $ids, string $table): void
    {
        if ($ids === []) return;

        $uniqueIds = array_values(array_unique($ids));
        $count = DB::table($table)
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $uniqueIds)
            ->count();

        abort_unless($count === count($uniqueIds), 422);
    }

    private function replaceRestrictions(ApiKey $key, array $data): void
    {
        DB::transaction(function () use ($key, $data): void {
            $this->replaceRestrictionTable('api_key_player_restrictions', 'player_id', $key->id, $data['player_restrictions'] ?? []);
            $this->replaceRestrictionTable('api_key_server_restrictions', 'server_id', $key->id, $data['server_restrictions'] ?? []);
            $this->replaceRestrictionTable('api_key_season_restrictions', 'season_id', $key->id, $data['season_restrictions'] ?? []);
        });
    }

    private function replaceRestrictionTable(string $table, string $column, string $keyId, array $ids): void
    {
        DB::table($table)->where('api_key_id', $keyId)->delete();

        if ($ids === []) return;

        DB::table($table)->insert(array_map(
            fn (string $id) => ['api_key_id' => $keyId, $column => $id],
            array_values(array_unique($ids)),
        ));
    }

    private function serializedKey(ApiKey $key): ApiKey
    {
        $key->setAttribute('scopes', DB::table('api_key_scopes')->where('api_key_id', $key->id)->pluck('scope')->values());
        $key->setAttribute('player_restrictions', DB::table('api_key_player_restrictions')->where('api_key_id', $key->id)->pluck('player_id')->values());
        $key->setAttribute('server_restrictions', DB::table('api_key_server_restrictions')->where('api_key_id', $key->id)->pluck('server_id')->values());
        $key->setAttribute('season_restrictions', DB::table('api_key_season_restrictions')->where('api_key_id', $key->id)->pluck('season_id')->values());

        return $key;
    }
}
