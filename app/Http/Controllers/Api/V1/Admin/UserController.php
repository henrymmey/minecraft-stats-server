<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController
{
    public function index(Request $request): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');

        $users = DB::table('workspace_memberships')
            ->join('users', 'users.id', '=', 'workspace_memberships.user_id')
            ->where('workspace_memberships.workspace_id', $workspaceId)
            ->orderBy('users.display_name')
            ->get([
                'users.id',
                'users.display_name',
                'users.email',
                'workspace_memberships.role',
                'users.last_login_at',
            ]);

        return response()->json(['data' => $users]);
    }

    public function update(Request $request, string $user): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace_id');
        $data = $request->validate([
            'role' => ['required', 'in:owner,admin,analyst,readonly'],
        ]);

        $updated = DB::table('workspace_memberships')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $user)
            ->update(['role' => $data['role']]);

        abort_unless($updated === 1, 404);

        return response()->json([
            'data' => [
                'user_id' => $user,
                'workspace_id' => $workspaceId,
                'role' => $data['role'],
            ],
        ]);
    }
}
