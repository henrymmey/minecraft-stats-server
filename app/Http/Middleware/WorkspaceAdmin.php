<?php

namespace App\Http\Middleware;

use App\Services\Authentication\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceAdmin
{
    public function __construct(private readonly WorkspaceContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 401);
        }

        $workspaceId = $this->context->current($user);
        $role = $this->context->role($user, $workspaceId);

        if (!$role || !in_array($role, ['owner', 'admin'], true)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Workspace administrator access is required.',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 403);
        }

        $request->attributes->set('workspace_id', $workspaceId);
        $request->attributes->set('workspace_role', $role);

        return $next($request);
    }
}
