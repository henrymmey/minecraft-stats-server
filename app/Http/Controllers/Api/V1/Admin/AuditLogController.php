<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $logs = DB::table('audit_logs')
            ->where('workspace_id', $request->attributes->get('workspace_id'))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return response()->json($logs);
    }
}
