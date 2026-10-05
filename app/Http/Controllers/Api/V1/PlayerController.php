<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController
{
    public function index(Request $request): JsonResponse
    {
        $key = $request->attributes->get('api_key');

        $query = Player::query()
            ->where('workspace_id', $key->workspace_id)
            ->where('public', true)
            ->orderBy('current_username');

        $restrictions = \DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->pluck('player_id');

        if ($restrictions->isNotEmpty()) {
            $query->whereIn('id', $restrictions);
        }

        if ($request->filled('search')) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], $request->string('search')->toString());
            $query->where('current_username', 'ilike', '%'.$term.'%');
        }

        $paginator = $query->paginate(
            min(max((int) $request->integer('per_page', 50), 1), 100),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Player $player): JsonResponse
    {
        $key = $request->attributes->get('api_key');

        abort_unless($player->workspace_id === $key->workspace_id && $player->public, 404);

        $restricted = \DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->exists();

        if ($restricted && !\DB::table('api_key_player_restrictions')
            ->where('api_key_id', $key->id)
            ->where('player_id', $player->id)
            ->exists()) {
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
