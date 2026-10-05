<?php

namespace App\Services\Authentication;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WorkspaceContext
{
    public function current(User $user): string
    {
        $selected = session('current_workspace_id');

        if ($selected && $this->hasMembership($user, $selected)) {
            return $selected;
        }

        $memberships = DB::table('workspace_memberships')
            ->where('user_id', $user->id)
            ->pluck('workspace_id');

        if ($memberships->count() === 1) {
            $workspaceId = (string) $memberships->first();
            session(['current_workspace_id' => $workspaceId]);

            return $workspaceId;
        }

        throw new AccessDeniedHttpException('No workspace is selected.');
    }

    public function role(User $user, string $workspaceId): ?string
    {
        $role = DB::table('workspace_memberships')
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->value('role');

        return $role ? (string) $role : null;
    }

    public function requireRole(User $user, array $roles): string
    {
        $workspaceId = $this->current($user);
        $role = $this->role($user, $workspaceId);

        if (!$role || !in_array($role, $roles, true)) {
            throw new AccessDeniedHttpException('You are not authorized to manage this workspace.');
        }

        return $workspaceId;
    }

    private function hasMembership(User $user, string $workspaceId): bool
    {
        return DB::table('workspace_memberships')
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->exists();
    }
}
