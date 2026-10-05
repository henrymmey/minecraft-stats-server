<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ApiKeys\ApiKeyAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController
{
    public function __construct(private readonly ApiKeyAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $key = $request->attributes->get('api_key');
        $season = \App\Models\Season::query()
            ->where('workspace_id', $key->workspace_id)
            ->where(fn ($query) => $query
                ->where('id', $request->string('season'))
                ->orWhere('slug', $request->string('season'))
            )
            ->firstOrFail();

        $seasonRestricted = \DB::table('api_key_season_restrictions')
            ->where('api_key_id', $key->id)
            ->exists();

        if ($seasonRestricted && !\DB::table('api_key_season_restrictions')
            ->where('api_key_id', $key->id)
            ->where('season_id', $season->id)
            ->exists()) {
            abort(403);
        }

        $statKey = $request->string('stat')->toString();
        $definition = \DB::table('stat_definitions')
            ->where('workspace_id', $key->workspace_id)
            ->where('key', $statKey)
            ->where('public', true)
            ->firstOrFail();

        $query = \DB::table('player_stats')
            ->join('players', 'players.id', '=', 'player_stats.player_id')
            ->where('player_stats.season_id', $season->id)
            ->where('player_stats.stat_definition_id', $definition->id)
            ->where('players.public', true);

        $playerRestrictions = \DB::table('api_key_uuid_restrictions')
            ->where('api_key_id', $key->id)
            ->pluck('minecraft_uuid');

        if ($playerRestrictions->isNotEmpty()) {
            $query->whereIn('players.minecraft_uuid', $playerRestrictions);
        }

        $limit = min(max((int) $request->integer('limit', 10), 1), 100);

        $entries = $query
            ->orderByDesc('player_stats.value')
            ->limit($limit)
            ->get([
                'players.id',
                'players.minecraft_uuid as uuid',
                'players.current_username as username',
                'players.public',
                'player_stats.value',
            ])
            ->values()
            ->map(fn ($row, $index) => [
                'rank' => $index + 1,
                'player' => $row,
                'value' => (int) $row->value,
            ]);

        return response()->json([
            'season' => $season,
            'stat' => $statKey,
            'entries' => $entries,
        ]);
    }
}
