<?php

namespace App\Services\ApiKeys;

use App\Models\ApiKey;
use Illuminate\Support\Str;

class ApiKeyService
{
    private const TYPES = ['client', 'website', 'integration'];

    public function create(array $attributes): array
    {
        $id = (string) Str::uuid();
        $secret = $this->randomSecret();
        $type = $attributes['type'] ?? 'client';

        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported API key type.');
        }

        $token = "mst_{$type}_{$id}_{$secret}";

        $key = ApiKey::query()->create([
            'id' => $id,
            'workspace_id' => $attributes['workspace_id'],
            'name' => $attributes['name'],
            'prefix' => "mst_{$type}_".Str::substr($id, 0, 8),
            'hash' => $this->hashSecret($secret),
            'description' => $attributes['description'] ?? null,
            'enabled' => true,
            'expires_at' => $attributes['expires_at'] ?? null,
            'created_by' => $attributes['created_by'] ?? null,
        ]);

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

        if (!$key || !hash_equals($key->hash, $this->hashSecret($secret))) {
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
