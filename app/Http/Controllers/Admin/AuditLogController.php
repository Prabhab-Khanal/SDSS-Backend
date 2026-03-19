<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved',
            'data'    => AuditLogResource::collection($logs),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Audit log entry',
            'data'    => new AuditLogResource($auditLog),
        ]);
    }
}
