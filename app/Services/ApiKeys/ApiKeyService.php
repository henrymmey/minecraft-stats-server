<?php

namespace App\Services\ApiKeys;

use App\Models\ApiKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiKeyService
{
    public const TYPES = ['client', 'website', 'integration'];

    public const SCOPES = [
        'ingest:write',
        'players:read',
        'stats:read',
        'events:read',
        'sessions:read',
        'leaderboards:read',
        'presence:read',
    ];

    public function create(array $attributes): array
    {
        $id = (string) Str::uuid();
        $secret = $this->randomSecret();
        $type = $attributes['type'] ?? 'client';

        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported API key type.');
        }

        $scopes = array_values(array_unique($attributes['scopes'] ?? []));

        foreach ($scopes as $scope) {
            if (!in_array($scope, self::SCOPES, true)) {
                throw new \InvalidArgumentException("Unsupported scope: {$scope}");
            }
        }

        if ($type === 'client' && !in_array('ingest:write', $scopes, true)) {
            throw new \InvalidArgumentException('Client keys require the ingest:write scope.');
        }

        $token = "mst_{$type}_{$id}_{$secret}";

        $key = DB::transaction(function () use ($attributes, $id, $secret, $type, $scopes): ApiKey {
            $key = ApiKey::query()->create([
                'id' => $id,
                'workspace_id' => $attributes['workspace_id'],
                'name' => $attributes['name'],
                'type' => $type,
                'prefix' => "mst_{$type}_".Str::substr($id, 0, 8),
                'hash' => $this->hashSecret($secret),
                'description' => $attributes['description'] ?? null,
                'enabled' => true,
                'expires_at' => $attributes['expires_at'] ?? null,
                'created_by' => $attributes['created_by'] ?? null,
            ]);

            if ($scopes !== []) {
                DB::table('api_key_scopes')->insert(
                    array_map(fn (string $scope) => [
                        'api_key_id' => $key->id,
                        'scope' => $scope,
                    ], $scopes),
                );
            }

            return $key;
        });

        return [$key, $token];
    }

    public function resolve(string $token): ?ApiKey
    {
        $parts = explode('_', $token, 4);

        if (count($parts) !== 4 || $parts[0] !== 'mst') {
            return null;
        }

        [, $type, $id, $secret] = $parts;

        if (!in_array($type, self::TYPES, true) || !Str::isUuid($id) || $secret === '') {
            return null;
        }

        $key = ApiKey::query()->find($id);

        if (!$key || $key->type !== $type || !hash_equals($key->hash, $this->hashSecret($secret))) {
            return null;
        }

        return $key;
    }

    private function hashSecret(string $secret): string
    {
        $pepper = (string) config('services.api_keys.pepper', '');

        return hash('sha256', $pepper.$secret);
    }

    private function randomSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
