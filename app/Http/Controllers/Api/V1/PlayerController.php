<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Player;
use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController
{
    public function index(Request $request): JsonResponse
    {
        $key = $request->attributes->get('api_key');
        $query = Player::query()
            ->where('workspace_id', $key->workspace_id)
            ->orderBy('current_username');

        $restrictions = \DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->pluck('player_id');

        if ($restrictions->isNotEmpty()) {
            $query->whereIn('id', $restrictions);
        }

        if ($request->filled('search')) {
            $query->where('current_username', 'ilike', '%'.str_replace('%', '\\%', $request->string('search')).'%');
        }

        return response()->json([
            'data' => $query->paginate(min((int) $request->integer('per_page', 50), 100)),
        ]);
    }

    public function show(Request $request, Player $player): JsonResponse
    {
        $key = $request->attributes->get('api_key');

        abort_unless($player->workspace_id === $key->workspace_id, 404);

        $restricted = \DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->exists();

        if ($restricted && !$key->restrictedPlayers()->whereKey($player->id)->exists()) {
            abort(403);
        }

        return response()->json([
            'id' => $player->id,
            'uuid' => $player->minecraft_uuid,
            'username' => $player->current_username,
            'public' => $player->public,
        ]);
    }
}
