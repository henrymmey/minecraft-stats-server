<?php

namespace Tests\Feature;

use App\Services\ApiKeys\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_client_key_can_ingest_for_an_allowed_player(): void
    {
        [$workspaceId, $serverId, $seasonId, $playerId] = $this->seedWorkspace();

        [, $token] = app(ApiKeyService::class)->create([
            'workspace_id' => $workspaceId,
            'name' => 'Client',
            'type' => 'client',
            'scopes' => ['ingest:write'],
        ]);

        DB::table('api_key_player_restrictions')->insert([
            'api_key_id' => DB::table('api_keys')->where('workspace_id', $workspaceId)->value('id'),
            'player_id' => $playerId,
        ]);

        $playerUuid = DB::table('players')->where('id', $playerId)->value('minecraft_uuid');
        $payload = $this->payload($playerUuid);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ingest/batch', $payload)
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('player_stats', [
            'season_id' => $seasonId,
            'player_id' => $playerId,
            'value' => 42,
        ]);
    }

    public function test_restricted_client_key_cannot_create_or_ingest_for_another_player(): void
    {
        [$workspaceId, , , $allowedPlayerId] = $this->seedWorkspace();
        $otherUuid = (string) Str::uuid();

        [, $token] = app(ApiKeyService::class)->create([
            'workspace_id' => $workspaceId,
            'name' => 'Restricted',
            'type' => 'client',
            'scopes' => ['ingest:write'],
        ]);

        $keyId = DB::table('api_keys')->where('workspace_id', $workspaceId)->value('id');

        DB::table('api_key_player_restrictions')->insert([
            'api_key_id' => $keyId,
            'player_id' => $allowedPlayerId,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ingest/batch', $this->payload($otherUuid));

        $response->assertStatus(403);

        $this->assertDatabaseMissing('players', [
            'workspace_id' => $workspaceId,
            'minecraft_uuid' => $otherUuid,
        ]);
    }

    public function test_replaying_an_event_does_not_duplicate_it(): void
    {
        [$workspaceId, , , $playerId] = $this->seedWorkspace();

        [, $token] = app(ApiKeyService::class)->create([
            'workspace_id' => $workspaceId,
            'name' => 'Client',
            'type' => 'client',
            'scopes' => ['ingest:write'],
        ]);

        $payload = $this->payload(
            DB::table('players')->where('id', $playerId)->value('minecraft_uuid'),
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ingest/batch', $payload)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ingest/batch', $payload)
            ->assertOk();

        $this->assertSame(
            1,
            DB::table('events')->where('client_event_id', $payload['events'][0]['id'])->count(),
        );
    }

    private function seedWorkspace(): array
    {
        $workspaceId = (string) Str::uuid();
        $serverId = (string) Str::uuid();
        $seasonId = (string) Str::uuid();
        $playerId = (string) Str::uuid();
        $now = now();

        DB::table('workspaces')->insert([
            'id' => $workspaceId,
            'name' => 'Test',
            'slug' => 'test-'.Str::lower(Str::random(6)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('servers')->insert([
            'id' => $serverId,
            'workspace_id' => $workspaceId,
            'name' => 'test',
            'hostname' => 'play.example.test',
            'port' => 25565,
            'display_name' => 'Test Server',
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('seasons')->insert([
            'id' => $seasonId,
            'workspace_id' => $workspaceId,
            'name' => 'Test Season',
            'slug' => 'test-season',
            'active' => true,
            'started_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('players')->insert([
            'id' => $playerId,
            'workspace_id' => $workspaceId,
            'minecraft_uuid' => (string) Str::uuid(),
            'current_username' => 'TestPlayer',
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'public' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$workspaceId, $serverId, $seasonId, $playerId];
    }

    private function payload(string $uuid): array
    {
        return [
            'protocol_version' => 1,
            'client' => [
                'mod_version' => '0.1.0',
                'minecraft_version' => '26.1',
                'fabric_loader_version' => '0.19.5',
            ],
            'player' => [
                'uuid' => $uuid,
                'username' => 'TestPlayer',
            ],
            'server' => [
                'hostname' => 'play.example.test',
                'port' => 25565,
            ],
            'season' => null,
            'session_id' => (string) Str::uuid(),
            'observed_at' => now()->toIso8601String(),
            'stats' => [
                [
                    'key' => 'minecraft:custom:test',
                    'value' => 42,
                ],
            ],
            'events' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'TEST_EVENT',
                    'occurred_at' => now()->toIso8601String(),
                    'payload' => ['test' => true],
                ],
            ],
        ];
    }
}
