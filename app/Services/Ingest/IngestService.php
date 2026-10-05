<?php

namespace App\Services\Ingest;

use App\Models\ApiKey;
use App\Models\GameSession;
use App\Models\MinecraftServer;
use App\Models\Player;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IngestService
{
    public function handle(array $payload, ApiKey $key, Request $request): array
    {
        $this->assertScope($key, 'ingest:write');

        return DB::transaction(function () use ($payload, $key, $request): array {
            $workspaceId = $key->workspace_id;
            $minecraftUuid = Str::lower($payload['player']['uuid']);

            $restrictedPlayerIds = DB::table('api_key_player_restrictions')
                ->where('api_key_id', $key->id)
                ->pluck('player_id');

            $playerQuery = Player::query()
                ->where('workspace_id', $workspaceId)
                ->where('minecraft_uuid', $minecraftUuid);

            if ($restrictedPlayerIds->isNotEmpty()) {
                $playerQuery->whereIn('id', $restrictedPlayerIds->all());
            }

            $player = $playerQuery->first();

            if (!$player) {
                if ($restrictedPlayerIds->isNotEmpty()) {
                    throw new AccessDeniedHttpException('The API key is not authorized for this player.');
                }

                $player = Player::query()->create([
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $workspaceId,
                    'minecraft_uuid' => $minecraftUuid,
                    'current_username' => $payload['player']['username'],
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'public' => true,
                ]);
            } else {
                $player->forceFill([
                    'current_username' => $payload['player']['username'],
                    'last_seen_at' => now(),
                ])->save();
            }

            $this->upsertAlias($player, $payload['player']['username']);

            $hostname = Str::lower(rtrim($payload['server']['hostname'], '.'));

            $serverQuery = MinecraftServer::query()
                ->where('workspace_id', $workspaceId)
                ->where('hostname', $hostname)
                ->where('port', $payload['server']['port'])
                ->where('enabled', true);

            $serverRestrictions = DB::table('api_key_server_restrictions')
                ->where('api_key_id', $key->id)
                ->pluck('server_id');

            if ($serverRestrictions->isNotEmpty()) {
                $serverQuery->whereIn('id', $serverRestrictions->all());
            }

            $server = $serverQuery->first();

            if (!$server) {
                if ($serverRestrictions->isNotEmpty()) {
                    throw new AccessDeniedHttpException('The API key is not authorized for this server.');
                }

                throw new NotFoundHttpException('The reported server is not registered.');
            }

            $season = Season::query()
                ->where('workspace_id', $workspaceId)
                ->where('active', true)
                ->when($payload['season'] ?? null, fn ($query, $hint) => $query->where(fn ($q) => $q
                    ->where('slug', $hint)
                    ->orWhere('name', $hint)
                ))
                ->first();

            if (!$season) {
                throw new NotFoundHttpException('No active season is configured for this workspace.');
            }

            $seasonRestrictions = DB::table('api_key_season_restrictions')
                ->where('api_key_id', $key->id)
                ->pluck('season_id');

            if ($seasonRestrictions->isNotEmpty() && !$seasonRestrictions->contains($season->id)) {
                throw new AccessDeniedHttpException('The API key is not authorized for this season.');
            }

            $session = GameSession::query()->firstOrNew([
                'player_id' => $player->id,
                'client_session_id' => $payload['session_id'],
            ]);

            if (!$session->exists) {
                $session->fill([
                    'id' => (string) Str::uuid(),
                    'season_id' => $season->id,
                    'server_id' => $server->id,
                    'started_at' => $payload['observed_at'],
                    'last_seen_at' => $payload['observed_at'],
                    'client_version' => $payload['client']['mod_version'],
                    'minecraft_version' => $payload['client']['minecraft_version'],
                    'mod_version' => $payload['client']['mod_version'],
                ])->save();
            } else {
                $session->forceFill([
                    'season_id' => $season->id,
                    'server_id' => $server->id,
                    'last_seen_at' => $payload['observed_at'],
                    'client_version' => $payload['client']['mod_version'],
                    'minecraft_version' => $payload['client']['minecraft_version'],
                    'mod_version' => $payload['client']['mod_version'],
                ])->save();
            }

            foreach ($payload['stats'] ?? [] as $observation) {
                $definition = DB::table('stat_definitions')
                    ->where('workspace_id', $workspaceId)
                    ->where('key', $observation['key'])
                    ->first();

                if (!$definition) {
                    $definitionId = (string) Str::uuid();

                    DB::table('stat_definitions')->insert([
                        'id' => $definitionId,
                        'workspace_id' => $workspaceId,
                        'key' => $observation['key'],
                        'name' => $observation['key'],
                        'category' => $this->statCategory($observation['key']),
                        'unit' => null,
                        'description' => null,
                        'public' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $definitionId = $definition->id;
                }

                DB::table('player_stats')->upsert(
                    [[
                        'season_id' => $season->id,
                        'player_id' => $player->id,
                        'stat_definition_id' => $definitionId,
                        'value' => $observation['value'],
                        'updated_at' => now(),
                    ]],
                    ['season_id', 'player_id', 'stat_definition_id'],
                    ['value', 'updated_at'],
                );

                DB::table('stat_history')->insert([
                    'id' => (string) Str::uuid(),
                    'season_id' => $season->id,
                    'player_id' => $player->id,
                    'stat_definition_id' => $definitionId,
                    'value' => $observation['value'],
                    'observed_at' => $payload['observed_at'],
                ]);
            }

            foreach ($payload['events'] ?? [] as $event) {
                DB::table('events')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'season_id' => $season->id,
                    'player_id' => $player->id,
                    'session_id' => $session->id,
                    'client_event_id' => $event['id'],
                    'type' => $event['type'],
                    'occurred_at' => $event['occurred_at'],
                    'payload' => isset($event['payload']) ? json_encode($event['payload']) : null,
                ]);
            }

            $key->forceFill(['last_used_at' => now()])->save();

            return [
                'accepted' => true,
                'request_id' => (string) ($request->attributes->get('request_id') ?? Str::uuid()),
                'server_time' => now()->toIso8601String(),
                'next_upload_after' => 60,
            ];
        });
    }

    private function assertScope(ApiKey $key, string $required): void
    {
        if (!$key->hasScope($required)) {
            throw new AccessDeniedHttpException('The API key does not have the required scope.');
        }
    }

    private function upsertAlias(Player $player, string $username): void
    {
        $now = now();

        $existing = DB::table('player_aliases')
            ->where('player_id', $player->id)
            ->where('username', $username)
            ->exists();

        if ($existing) {
            DB::table('player_aliases')
                ->where('player_id', $player->id)
                ->where('username', $username)
                ->update(['last_seen_at' => $now]);
        } else {
            DB::table('player_aliases')->insert([
                'player_id' => $player->id,
                'username' => $username,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        }
    }

    private function statCategory(string $key): string
    {
        $parts = explode(':', $key, 3);

        return $parts[1] ?? 'custom';
    }
}
