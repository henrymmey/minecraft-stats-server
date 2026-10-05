<?php

namespace App\Services\ApiKeys;

use App\Models\ApiKey;
use Illuminate\Support\Facades\DB;

class ApiKeyAccessService
{
    public function allowsPlayer(ApiKey $key, string $playerId): bool
    {
        return $this->allowsResource($key, 'api_key_player_restrictions', 'player_id', $playerId);
    }

    public function allowsServer(ApiKey $key, string $serverId): bool
    {
        return $this->allowsResource($key, 'api_key_server_restrictions', 'server_id', $serverId);
    }

    public function allowsSeason(ApiKey $key, string $seasonId): bool
    {
        return $this->allowsResource($key, 'api_key_season_restrictions', 'season_id', $seasonId);
    }

    private function allowsResource(ApiKey $key, string $table, string $column, string $id): bool
    {
        $hasRestrictions = DB::table($table)->where('api_key_id', $key->id)->exists();

        if (!$hasRestrictions) {
            return true;
        }

        return DB::table($table)
            ->where('api_key_id', $key->id)
            ->where($column, $id)
            ->exists();
    }
}
