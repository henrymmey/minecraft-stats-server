<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsController
{
    public function player(Request $request, Player $player): JsonResponse
    {
        $key = $request->attributes->get('api_key');
        abort_unless($player->workspace_id === $key->workspace_id, 404);

        $playerRestricted = \DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->exists();

        if ($playerRestricted && !\DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->where('player_id', $player->id)
            ->exists()) {
            abort(403);
        }

        $season = $request->string('season')->toString();

        $seasonModel = \App\Models\Season::query()
            ->where('workspace_id', $key->workspace_id)
            ->where(fn ($query) => $query->where('id', $season)->orWhere('slug', $season))
            ->firstOrFail();

        $seasonRestricted = \DB::table('api_key_season_restrictions')
            ->where('api_key_id', $key->id)
            ->exists();

        if ($seasonRestricted && !\DB::table('api_key_season_restrictions')
            ->where('api_key_id', $key->id)
            ->where('season_id', $seasonModel->id)
            ->exists()) {
            abort(403);
        }

        $stats = \DB::table('player_stats')
            ->join('stat_definitions', 'stat_definitions.id', '=', 'player_stats.stat_definition_id')
            ->where('player_stats.player_id', $player->id)
            ->where('player_stats.season_id', $seasonModel->id)
            ->where('stat_definitions.workspace_id', $key->workspace_id)
            ->where('stat_definitions.public', true)
            ->orderBy('stat_definitions.key')
            ->get([
                'stat_definitions.key',
                'player_stats.value',
                'player_stats.updated_at',
            ]);

        return response()->json([
            'player' => [
                'id' => $player->id,
                'uuid' => $player->minecraft_uuid,
                'username' => $player->current_username,
                'public' => $player->public,
            ],
            'season' => $seasonModel,
            'stats' => $stats,
        ]);
    }
}
