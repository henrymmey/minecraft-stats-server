<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonController
{
    public function index(Request $request): JsonResponse
    {
        $key = $request->attributes->get('api_key');

        $query = Season::query()
            ->where('workspace_id', $key->workspace_id)
            ->orderByDesc('active')
            ->orderByDesc('started_at');

        $restrictions = \DB::table('api_key_season_restrictions')
            ->where('api_key_id', $key->id)
            ->pluck('season_id');

        if ($restrictions->isNotEmpty()) {
            $query->whereIn('id', $restrictions);
        }

        return response()->json([
            'data' => $query->get([
                'id', 'name', 'slug', 'active', 'started_at', 'ended_at',
            ]),
        ]);
    }
}
