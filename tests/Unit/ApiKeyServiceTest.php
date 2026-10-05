<?php

namespace Tests\Unit;

use App\Models\ApiKey;
use App\Services\ApiKeys\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_resolves_a_client_key(): void
    {
        config(['services.api_keys.pepper' => 'test-pepper']);

        $workspaceId = (string) Str::uuid();

        DB::table('workspaces')->insert([
            'id' => $workspaceId,
            'name' => 'Test',
            'slug' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$key, $token] = app(ApiKeyService::class)->create([
            'workspace_id' => $workspaceId,
            'name' => 'Test Client',
            'type' => 'client',
            'scopes' => ['ingest:write'],
        ]);

        $this->assertInstanceOf(ApiKey::class, $key);
        $this->assertStringStartsWith('mst_client_', $token);
        $this->assertNotSame($token, $key->hash);

        $resolved = app(ApiKeyService::class)->resolve($token);

        $this->assertNotNull($resolved);
        $this->assertSame($key->id, $resolved->id);
        $this->assertTrue($resolved->hasScope('ingest:write'));
    }

    public function test_it_rejects_a_tampered_secret(): void
    {
        config(['services.api_keys.pepper' => 'test-pepper']);

        $workspaceId = (string) Str::uuid();

        DB::table('workspaces')->insert([
            'id' => $workspaceId,
            'name' => 'Test',
            'slug' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [, $token] = app(ApiKeyService::class)->create([
            'workspace_id' => $workspaceId,
            'name' => 'Test Client',
            'type' => 'client',
            'scopes' => ['ingest:write'],
        ]);

        $tampered = substr($token, 0, -1).'X';

        $this->assertNull(app(ApiKeyService::class)->resolve($tampered));
    }

    public function test_it_rejects_a_changed_key_type(): void
    {
        config(['services.api_keys.pepper' => 'test-pepper']);

        $workspaceId = (string) Str::uuid();

        DB::table('workspaces')->insert([
            'id' => $workspaceId,
            'name' => 'Test',
            'slug' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [, $token] = app(ApiKeyService::class)->create([
            'workspace_id' => $workspaceId,
            'name' => 'Test Client',
            'type' => 'client',
            'scopes' => ['ingest:write'],
        ]);

        $changedType = preg_replace('/^mst_client_/', 'mst_website_', $token);

        $this->assertNull(app(ApiKeyService::class)->resolve($changedType));
    }
}
