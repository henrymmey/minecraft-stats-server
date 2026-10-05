<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController
{
    public function index(Request $request): JsonResponse
    {
        $query = Player::query()
            ->where('workspace_id', $request->attributes->get('workspace_id'))
            ->orderBy('current_username');

        if ($request->filled('search')) {
            $query->where('current_username', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('search')->toString()).'%');
        }

        return response()->json(['data' => $query->limit(500)->get([
            'id',
            'minecraft_uuid',
            'current_username',
            'public',
            'last_seen_at',
        ])]);
    }
}
