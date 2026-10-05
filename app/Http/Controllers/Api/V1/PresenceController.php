<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController
{
    public function index(Request $request): JsonResponse
    {
        $key = $request->attributes->get('api_key');

        $query = \DB::table('players')
            ->join('game_sessions', 'game_sessions.player_id', '=', 'players.id')
            ->where('players.workspace_id', $key->workspace_id)
            ->where('players.public', true)
            ->whereNull('game_sessions.ended_at')
            ->where('game_sessions.last_seen_at', '>=', now()->subMinutes(2))
            ->select([
                'players.id',
                'players.minecraft_uuid as uuid',
                'players.current_username as username',
                'players.public',
                'game_sessions.last_seen_at',
            ]);

        $restrictions = \DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->pluck('player_id');

        if ($restrictions->isNotEmpty()) {
            $query->whereIn('players.id', $restrictions);
        }

        return response()->json([
            'data' => $query->orderBy('players.current_username')->get()->map(fn ($row) => [
                'player' => $row,
                'online' => true,
                'last_seen_at' => $row->last_seen_at,
            ]),
        ]);
    }
}
